# GIO Dag Client

Laravel package of Gio Dag client (http://docs.dag.iijgio.com/analysis/library/php.html)

# Install

Require library

```
$ composer require fileio/dag-client:0.0.2
```

# Setup

Add `dag` directive to `config/filesystems.php` under `disks`

```
'dag' => [
    'driver' => 'dag',
    'key'    => 'access_key',
    'secret' => 'secret_key',
    'bucket' => 'bukcet_name',
],
```

Register provider in `config/app.php` under providers

```
Gio\IijDagClient\Providers\GioServiceProvider::class
```

Register Facade in `config/app.php` under aliases

```
'GioIijDagClient' => Gio\IijDagClient\Facade\GioIijDagClient::class
```

# Multipart file download

```
GioIijDagClient::readStreamAsync($path,
    function($data) {
        // called everytime data is downloaded from dag
    },
    function() use (&$finished) {
        // called when download finishes
    }
);
```
