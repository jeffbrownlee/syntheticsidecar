<?php

set_time_limit(0);

require_once 'vendor/autoload.php';

$client = new WebSocket\Client("wss://stabilityai-stable-diffusion-1.hf.space/queue/join");
$session_key = substr(uniqid(), 0, 11);

while (true) {
    try {
        $message = $client->receive();
        $data = json_decode($message);

        switch ($data->msg)
        {
            case "send_hash":
                $client->send(json_encode(['session_hash' => $session_key, 'fn_index' => 3]));
                break;
            case "send_data":
                $client->send(json_encode(['data'=>[ $argv[1] ], 'session_hash' => $session_key, 'fn_index' => 3]));
                break;
            case "process_completed":
                foreach ($data->output->data[0] as $k=>$image_data)
                {
                    $image_data = substr($image_data,strpos($image_data,",")+1);
                    file_put_contents("/tmp/$session_key.$k.jpg", base64_decode(str_replace(' ','+',$image_data)));
                }                   
                $client->close();
                die($session_key);
        }
      } catch (\WebSocket\ConnectionException $e) { }
}

$client->close();
die();