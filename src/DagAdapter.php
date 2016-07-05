<?php

namespace Gio\IijDagClient;

require_once dirname(__FILE__) . "/../vendor/autoload.php";

use Gio\IijDagClient\NotImplementedException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use Illuminate\Foundation\Bus\DispatchesJobs;

class DagAdapter extends AbstractAdapter
{
    use DispatchesJobs;

    private $client;
    private $bucket;

    public function __construct($config)
    {
        $this->client = new \Dag\Client($config['key'], $config['secret']);
        $this->bucket = $this->client->bucket($config['bucket']);
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $localPath
     * @param Config $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $localPath, Config $config)
    {
        $object = $this->bucket->object($path);

        $params = [];
        $size = Storage::size($localPath);
        if ($this->isSizeBiggerForMultiPart($size))
        {
            $params['multipart'] = true;
        }

        $object->write($localPath, $params);
    }

    /**
     * Write a new file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function writeStream($path, $resource, Config $config)
    {
        $stat = fstat($resource);
        $size = $stat['size'];

        $params = [];
        if ($this->isSizeBiggerForMultiPart($size))
        {
            $params['multipart'] = true;
        }

        $object = $this->bucket->object($path);
        return $object->writeStream($resource, $params);
    }

    private function isSizeBiggerForMultiPart($size)
    {
        $mp_threshold = 100 * pow(1024, 2); // 100MB
        if ($size >= $mp_threshold)
        {
            return true;
        }
        return false;
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config
     * @throws NotImplementedException
     *
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     * @throws NotImplementedException
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     * @throws NotImplementedException
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {
        throw new NotImplementedException();
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     * @throws NotImplementedException
     *
     * @return bool
     */
    public function copy($path, $newpath)
    {
        throw new NotImplementedException();
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path)
    {
        $object = $this->bucket->object($path);
        $object->delete();
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDir($dirname)
    {
        $object = $this->bucket->object($dirname);
        $object->delete();
    }

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     * @throws NotImplementedException
     *
     * @return array|false
     */
    public function createDir($dirname, Config $config)
    {
        $object = $this->bucket->object($dirname . '/.keep');
        return $object->write(base_path('vendor/gio/iij-dag-client/resource/.keep'));
    }

    /**
     * @param $dirname
     * @param Config $config
     * @return array|false
     */
    public function makeDirectory($dirname, Config $config)
    {
        return $this->createDir($dirname, $config);
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     * @throws NotImplementedException
     *
     * @return array|false file meta data
     */
    public function setVisibility($path, $visibility)
    {
        throw new NotImplementedException();
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return array|bool|null
     */
    public function has($path)
    {
        $meta = null;
        try
        {
            $meta = $this->getMetadata($path);
        }
        catch (\Exception $e)
        {
            // DO nothing
        }
        return $meta != null;
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {
        $object = $this->bucket->object($path);
        return [
            'contents' => $object->read(),
        ];
    }

    /**
     * @param string $path
     * @return resource
     */
    public function readStream($path)
    {
        $this->deleteTmp(60);

        $size = $this->getSize($path);
        $object = $this->bucket->object($path);

        $tmpFilePath = 'chunk_tmp/' . $path;
        $absoluteTmpFilePath = storage_path('app/' . $tmpFilePath . ':' . Carbon::now()->timestamp);

        $chunkSize = 10 * pow(1024, 2); // 10MB
        $currentPosition = 0;

        try
        {
            if(!file_exists(dirname($absoluteTmpFilePath)))
            {
                mkdir(dirname($absoluteTmpFilePath), 0777, true);
            }

            touch($absoluteTmpFilePath);
            $fp = fopen($absoluteTmpFilePath, 'w');

            while($currentPosition <= $size)
            {
                // range get of object data
                $data = $object->read([$currentPosition, $chunkSize]);

                // $data を tmp file に書き込む
                fwrite($fp, $data);
                $currentPosition += $chunkSize + 1;
            }

            fclose($fp);

            return [
                'stream' => fopen($absoluteTmpFilePath, 'r'),
            ];
        }
        catch(\Exception $e)
        {
            return false;
        }
    }

    /**
     * @param $path
     * @param $onUpdate
     * @param $onFinish
     * @return bool
     */
    public function readStreamAsync($path, $onUpdate, $onFinish)
    {
        $path = str_replace('./', '', $path);

        $size = $this->getSize($path);
        $object = $this->bucket->object($path);

        $chunkSize = 10 * pow(1024, 2); // 10MB
        $currentPosition = 0;

        try
        {
            while($currentPosition <= $size)
            {
                // range get of object data
                $nextPosition = $currentPosition + $chunkSize;
                $data = $object->read([$currentPosition, $nextPosition]);
                $onUpdate($data);

                $currentPosition = $nextPosition + 1;
            }

            $onFinish();
        }
        catch(\Exception $e)
        {
            Log::error($e);
            return false;
        }
    }

    /**
     * sec で指定されたものよりも古いファイルを削除する
     * @param $sec
     */
    public function deleteTmp($sec)
    {
        $files = Storage::disk('local')->allFiles('chunk_tmp/projects');
        foreach ($files as $file)
        {
            $info = explode(':', $file);
            $time = intval($info[count($info) - 1]);
            if ($time != 0)
            {
                $timeObject = Carbon::createFromTimestamp($time);
                $diff = Carbon::now()->diffInSeconds($timeObject);
                if ($diff  >= $sec)
                {
                    Storage::disk('local')->delete($file);
                }
            }
        }
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     * @throws NotImplementedException
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        throw new NotImplementedException();
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMetadata($path)
    {
        $object = $this->bucket->object($path);
        return $object->head();
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getSize($path)
    {
        $object = $this->bucket->object($path);
        $meta = $object->head();
        return $meta['Content-Length'];
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     * @throws NotImplementedException
     *
     * @return array|false
     */
    public function getMimetype($path)
    {
        throw new NotImplementedException();
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getTimestamp($path)
    {
        $object = $this->bucket->object($path);
        $meta = $object->head();
        $date = $meta['Last-Modified'];

        return Carbon::createFromFormat('D, d M Y H:i:s e', $date)->timestamp;
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     * @throws NotImplementedException
     *
     * @return array|false
     */
    public function getVisibility($path)
    {
        throw new NotImplementedException();
    }
}