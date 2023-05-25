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
    $package = unserialize($message);

    $id = $package['id'];
    $author = $package['author'];
    $title = $package['title'];
    $content = $package['content'];

    audit::log("Creating POST '$title' by $author on " . getenv('DOMAIN'));

    // Write content to file
    $filename = '/tmp/post_'.getenv('DOMAIN').'_'.uniqid().'.txt';
    file_put_contents($filename, $content);

    // Publish Content retreiving new post ID, remove temp file
    exec("cd /var/www/" . getenv('DOMAIN') . " && wp post create $filename --post_author=$author --post_title='$title' --post_status=publish --porcelain --allow-root", $post);
    unlink($filename);

    // Get URL for new post
    exec("cd /var/www/" . getenv('DOMAIN') . " && wp post get $post[0] --field=guid --allow-root", $url);
    $url = str_replace('http://', 'https://', $url[0]);

    // Create an image for the post
    exec("cd /var/www/syntheticsidecar && php generate.php '$title'", $hash);

    if (!empty($hash)) {
        exec("cd /var/www/" . getenv('DOMAIN') . " && wp media import '/tmp/$hash[0].0.jpg' --porcelain --allow-root", $featured);
        exec("cd /var/www/" . getenv('DOMAIN') . " && wp post meta add $post[0] _thumbnail_id $featured[0] --allow-root");

        unlink("/tmp/$hash[0].0.jpg");
        unlink("/tmp/$hash[0].1.jpg");
        unlink("/tmp/$hash[0].2.jpg");
        unlink("/tmp/$hash[0].3.jpg");
    }

    // Send the URL back to the social worker
    $pmq = new \queue\messagequeue('social');
    $pmq->send( serialize([ $id, $title, $url ]) );

    audit::log("Sending '$title' by $author to social worker.");
}
