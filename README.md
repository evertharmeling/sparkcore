sparkcore
=========

PHP classes who help interacting with the sparkcore. For example consume Server Side Events.

## Implementation

First create your own `Sparkhose` class which extends `SparkCore\Stream\Sparkhose`.

This library is implemented as an abstract class (Sparkhose) that is extended to utilize the SparkCore event stream.

This design choice was made to allow the utilization of the library with the minimal amount of code to be written, whilst keeping things in a PHP/OO-style way (ie: no callbacks, etc).

In its most simple form, you need to extended the class and implement an enqueueEvent() method:

``` php
<?php

namespace MyApp\MyBundle\Stream;

use SparkCore\Stream\Event\SparkEvent;
use SparkCore\Stream\Sparkhose as BaseSparkHose;

class Sparkhose extends BaseSparkhose
{
    /**
     * {@inheritdoc}
     *
     * @param SparkEvent $event
     */
    public function enqueueEvent(SparkEvent $sparkEvent)
    {
        // do something with $sparkEvent
    }
}
```

Now your able to initiate your own `Sparkhose` class:

``` php
<?php

use MyApp\MyBundle\Stream\Sparkhose;

$sparkhose = new SparkHose('spark_device_id', 'spark_access_token');
$sparkhose->consume();

```

### Specific Event

Optionally you can listen to a specific event by setting the 'event_name' on the Sparkhose object.

``` php
<?php

use MyApp\MyBundle\Stream\Sparkhose;

$sparkhose = new SparkHose('spark_device_id', 'spark_access_token');
$sparkhose->setEventName('event_name');
$sparkhose->consume();

```

### Logging

By passing a Logger (Psr/Log) instance to the Sparkhose object, you'll be able to see logging.

``` php
<?php

use MyApp\MyBundle\Logger\MyLogger;
use MyApp\MyBundle\Stream\Sparkhose;

$logger = new MyLogger();

$sparkhose = new SparkHose('spark_device_id', 'spark_access_token');
$sparkhose->setLogger($logger);
$sparkhose->consume();

```

Please note that this library is only intended to work in a CLI environment (not embedded in a web-script and run by your webserver) and will likely require some form of multi-processing (either pcntl_fork() or entirely separate processes and some knowledge of how multi-processing works on your and your hosting providers operating systems). See below for more details.

## Collecting and Processing

An important concept to understand is the separation of collection and processing functionality.

> To prevent latency problems and plan for scale, design your client with decoupled collection, processing and persistence components. The collection component should efficiently handle connecting to the Streaming API and retrieving responses, as well as reconnecting in the event of network failure, and hand-off statuses via an asynchronous queueing mechanism to application specific processing and persistence components. This component should be isolated from any subsequent downstream processing backlog or maintenance, otherwise queuing will occur in the Streaming API. Eventually your client will be disconnected, resulting in data loss.

For example, consume statuses from your queue or store of choice, parse them, extract the fields relevant to your application, etc. It is because of this recommendation that the only (required) method that need be implemented is enqueueStatus(). It is up to you to decide how you want to manage queueing of your raw statuses, but it is important that you just queue them and don't try to do full processing inline as this could cause your client to lag and get disconnected or (probably eventually) banned.
