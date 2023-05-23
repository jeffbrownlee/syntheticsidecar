<?php 

namespace queue;

class messagequeue 
{
	var $channel;
	var $connection;
	var $queue;
	var $exchange;
	var $callback;
	var $limit;
	
	public function __construct($queue, $exchange='', $limit=-1)
	{
		$this->queue = $queue;
		$this->exchange = $exchange;
		$this->limit =$limit;
	}
	
	public function send($message)
	{
		$this->connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(getenv('RABBITMQ_HOST'), getenv('RABBITMQ_PORT'), getenv('RABBITMQ_USERNAME'), getenv('RABBITMQ_PASSWORD'), getenv('RABBITMQ_VIRTUAL_HOST'));
		$this->channel = $this->connection->channel();
		$this->channel->queue_declare($this->queue,false,true,false,false);		

		$msg = new \PhpAmqpLib\Message\AMQPMessage($message);		

        $this->channel->basic_publish(
			$msg,				#message 
			$this->exchange,	#exchange
			$this->queue		#routing key (queue)
		);

		$this->channel->close();
		$this->connection->close();		
	}
	
	public function process($callback)
	{
		// Timeout / RPC Timeout set to 30 minutes for long-running jobs
		$this->connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(getenv('RABBITMQ_HOST'), getenv('RABBITMQ_PORT'), getenv('RABBITMQ_USERNAME'), getenv('RABBITMQ_PASSWORD'), getenv('RABBITMQ_VIRTUAL_HOST'), false, 'AMQPLAIN', null, 'en_US', 3.0, 1800.0, null, true, 1800);
		$this->channel = $this->connection->channel();
		$this->channel->queue_declare($this->queue,false,true,false,false);		
		$this->callback = $callback;
		
        $this->channel->basic_qos(
			null,   #prefetch size - prefetch window size in octets, null meaning "no specific limit"
			1,		#prefetch count - prefetch window in terms of whole messages
			null    #global - global=null to mean that the QoS settings should apply per-consumer, global=true to mean that the QoS settings should apply per-channel
		);		
		
        $this->channel->basic_consume(
            $this->queue,					#queue
            '',								#consumer tag - Identifier for the consumer, valid within the current channel. just string
            false,							#no local - TRUE: the server will not send messages to the connection that published them
            false,							#no ack, false - acks turned on, true - off.  send a proper acknowledgment from the worker, once we're done with a task
            false,							#exclusive - queues may only be accessed by the current connection
            false,							#no wait - TRUE: the server will not respond to the method. The client should not wait for a reply method
            array($this, 'callbackhandler')	#callback
		);
		
        while(count($this->channel->callbacks) && ($this->limit == -1 || $this->limit > 0)) {
			try {
				$this->channel->wait();
			}
			catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
			}
			catch (\PhpAmqpLib\Exception\AMQPRuntimeException $e) {
			}
        }		

		$this->channel->close();
		$this->connection->close();		
	}

	public function callbackhandler ($msg) {
		if ($this->limit > 0)
			$this->limit--;

		eval($this->callback . '("$msg->body");');
		$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
	}
}

class languageRequest
{
    var $type;
	var $id;
	var $language;
    
    function __construct($type, $id, $language)
    {
        $this->type = $type;
        $this->id = $id;
        $this->language = $language;
    }
}

class pictionRequest
{
    var $object;
	var $force;
    
    function __construct($object, $force)
    {
        $this->object = $object;
        $this->force = $force;
    }
}

class imageRequest
{
    var $object;
	var $media;
	var $restricted;
	var $force;

	function __construct($object, $media, $restricted, $force)
    {
        $this->object = $object;
        $this->media = $media;
        $this->restricted = $restricted;
        $this->force = $force;
    }
}
