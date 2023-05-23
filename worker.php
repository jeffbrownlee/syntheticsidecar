<?PHP

# Sidecar Worker
# Watches a work queue and publishes posts

set_time_limit(0);

require_once 'config.php';

require_once 'vendor/autoload.php';
require_once 'lib/audit.class.php';
require_once 'lib/messagequeue.class.php';

// Message queue handler
$io = new \queue\messagequeue('publish_'.getenv('DOMAIN'));
$io->process('post');

function post($message) 
{
    die(print_r(unserialize($message), true));

    [$id, $author, $title, $content] = unserialize($message);

    audit::log("Creating POST '$title' by $author on " . getenv('DOMAIN'));

    // Write content to file
    $filename = '/tmp/post_'.getenv('DOMAIN').'_'.md5().'.txt';
    file_put_contents($filename, $content);

    // Publish Content retreiving new post ID, remove temp file
    exec("cd /var/www/" . getenv('DOMAIN') . " && wp post create $filename --post_author=$author --post_title='$title' --post_status=publish --porcelain", $post);
    unlink($filename);

    // Get URL for new post
    exec("cd /var/www/" . getenv('DOMAIN') . " && wp post get $post --field=guid", $url);
    $url = str_replace('http://', 'https://', $url);

    // Send the URL back to the social worker
    $pmq = new \queue\messagequeue('social');
    $pmq->send( serialize([ $id, $title, $url ]) );

    audit::log("Sending '$title' by $author to social worker.");

    $mysqli->close();
}
