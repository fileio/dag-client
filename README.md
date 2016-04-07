# IIJ GIO Dag Client

# Install

Add following line to your composer.json

```
"repositories": [
    {
        "type": "vcs",
        "url": "git@komugi-gitlab.info:gio/iij-dag-client.git"
    }
]
```

```
$ composer require gio/iij-dag-client:0.0.1
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
Gio\IijDagClient\Facade\GioIijDagClient::class
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
