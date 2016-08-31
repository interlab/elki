<?php

chdir(__DIR__);

define( 't', time() );
const d = __DIR__;

require_once __DIR__ . '/vendor/autoload.php';

use \Symfony\Component\Yaml\Yaml;

// http://victor.4devs.io/en/web-scraping/scraping-data-with-goutte.html
// http://symfony.com/doc/current/components/dom_crawler.html
// http://docs.guzzlephp.org/en/latest/quickstart.html
// http://onedev.net/post/417
use \Goutte\Client;

// php path-to-Elki.php dirpath siteurl
// php C:\apache\php\localhost\www\elki\Elki.php "C:\apache\php\localhost\www\elk107" "http://localhost/elk107"

$zf = d . '/ElkArte_install.zip';
// $url_zf = 'http://github.com/elkarte/Elkarte/releases/download/v1.0.7/ElkArte_v1-0-7_install.zip';
$url_zf = 'https://github.com/elkarte/Elkarte/releases/download/v1.0.9/ElkArte_v1-0-9_install.zip';
// $url_zf_sha1 = 'B1CF32F1C633AA6F4031B7D487459ECEF1E3750C';
$url_zf_sha1 = 'e2b9ad30ca4894fc9f359407b6d093f154801772';

// Extract directory
if (isset($argv[1])) {
    if (!is_dir($argv[1])) {
        $extractdir = mkdir($argv[1]) ? $argv[1] : null;
    } else {
        $extractdir = $argv[1];
    }
}
$extractdir = !empty($extractdir) ? $extractdir : d . '/t1';

// Site url
$siteurl = isset($argv[2]) ? $argv[2] : 'http://localhost/elki/t1';
$url = $siteurl . '/install.php';

$config = Yaml::parse(file_get_contents(__DIR__ . '/config.yml'));
$db = array_map(function($a){ return str_replace('{{t}}', t, $a); }, $config['db']);
$admin = $config['admin'];
$demoboards = $config['demoboards'];

$step = 0;

function fixdberror($extractdir, $db)
{
    if ('mysql' === $db['db_type']) {
        $file = __DIR__ . '/Logging.php';
        $newfile = $extractdir . '/sources/Logging.php';
        if (!copy($file, $newfile)) {
            echo "не удалось скопировать $file...\n";
        }

        $mysqli = new mysqli($db['db_server'], $db['db_user'], $db["db_passwd"], $db["db_name"]);

        if (mysqli_connect_errno()) {
            printf("Подключение не удалось: %s\n", mysqli_connect_error());
            exit();
        }

        $mysqli->query('ALTER TABLE `'.$db['db_prefix'].'log_online` CHANGE `ip` `ip` VARBINARY(16) NOT NULL;');
        $mysqli->close();
    }
}

function is_dir_empty($dir)
{
    if ( ! is_readable($dir) ) {
        return null;
    }

    return (count(scandir($dir)) === 2);
}

function del_dir($dir)
{
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? del_dir("$dir/$file") : unlink("$dir/$file");
    }

    return rmdir($dir);
}

function findError($pageCrawler)
{
    if ($pageCrawler->filter('div.errorbox')->count()) {
        $pageCrawler->filter('div.errorbox strong')->each(function($node) {
            echo $node->text(), "\n";
        });

        $pageCrawler->filter('div.errorbox div')->each(function($node) {
            echo $node->text(), "\n";
        });

        die();
    }
}

function printStep($pageCrawler)
{
    findError($pageCrawler);
    $pageCrawler->filter('#main_steps .stepcurrent')->each(function($node) {
        print $node->text()."\n";
    });
}

function createDemoBoards(array $boards, Client $client)
{
    global $siteurl;

    // $faker = Faker\Factory::create();
    // echo $faker->name;
    // echo $faker->text;

    foreach ($boards as $board => $childs) {
        if (empty($bid)) {
            $bid = 1;
        }

        $crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=manageboards;sa=newboard;cat=1');
        $form = $crawler->selectButton('Add Board')->form([
            'board_name' => $board,
            'desc' => '',
            'placement' => 'after',
            'board_order' => $bid,
        ]);

        $pageCrawler = $client->submit($form, []);
        print("Create $board board.\n");

        $link = $pageCrawler->selectLink($board)->link()->getUri();
        preg_match('~board=(\d+)~iu', $link, $matches);
        $bid = $matches[1];

        if (!empty($childs)) {
            foreach ($childs as $b) {
                $crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=manageboards;sa=newboard;cat=1');
                $form = $crawler->selectButton('Add Board')->form([
                    'board_name' => $b,
                    'desc' => '',
                    'placement' => 'child',
                    'board_order' => $bid,
                ]);

                $pageCrawler = $client->submit($form, []);
                print("Create $b child of $board.\n");
            }
        }
    }
}

if ( ! file_exists($zf) ) {
    print("Download ElkArte\n");
    file_put_contents($zf, fopen($url_zf, 'r'));
    if (strtoupper(sha1_file($zf)) !== strtoupper($url_zf_sha1)) {
        echo strtoupper(sha1_file($zf));
        echo "\n" . strtoupper($url_zf_sha1) . "\n";
        die('Bad file!');
    }
}

if ( ! is_dir($extractdir) || is_dir_empty($extractdir)) {
    $zip = new \ZipArchive;
    $zip->open($zf);
    $zip->extractTo($extractdir);
    $zip->close();
}

$client = new Client();
$crawler = $client->request('GET', $url);
printStep($crawler);

// [Step] Click #contbutt
// $link = $crawler->selectLink('#contbutt')->link();
// $crawler = $client->click($link);
$buttonCrawler = $crawler->selectButton('Continue');
$form = $buttonCrawler->form();
$pageCrawler = $client->submit($form);
printStep($pageCrawler);
$step++;

// [Step]
$buttonCrawler = $pageCrawler->selectButton('Continue');
$form = $buttonCrawler->form();
// $form = $crawler->filter('form')->first();
// print_r($form->getValues());
// print_r($form->all()['attributes']);
// print_r($form->getPhpValues());
$pageCrawler = $client->submit($form, $db);
printStep($pageCrawler);
$step++;

// [Step]
$buttonCrawler = $pageCrawler->selectButton('Continue');
$form = $buttonCrawler->form();
$pageCrawler = $client->submit($form, ['mbname' => $config['forumname']]);
printStep($pageCrawler);
$pageCrawler->filter('.panel ul li')->each(function ($node) {
    print $node->text()."\n";
});
$step++;

// [Step]
$buttonCrawler = $pageCrawler->selectButton('Continue');
$form = $buttonCrawler->form();
$pageCrawler = $client->submit($form, []);
printStep($pageCrawler);
$step++;

// [Step]
$buttonCrawler = $pageCrawler->selectButton('Continue');
$form = $buttonCrawler->form();
$pageCrawler = $client->submit($form, [
    'username' => $admin['username'],
    'password1' => $admin['password1'],
    'password2' => $admin['password2'],
    'password3' => $admin['password3'],
    'email' => $admin['email'],
]);
printStep($pageCrawler);
$step++;

// [Step]
unlink($extractdir . '/install.php');
$step++;

// Установка завершена.
// Перейдём к настройкам.

// [Step]
// disable admin checks
$client = new Client();
$crawler = $client->request('GET', $siteurl);
$form = $crawler->selectButton('Log in')->form();
$pageCrawler = $client->submit($form, ['user' => $admin['username'], 'passwrd' => $admin['password1']]);
$step++;
print("Step $step: log in on site \n");

// [Step]
// Admin confirm password
$crawler = $client->request('GET', $siteurl . '/index.php?action=admin');
if ( $crawler->filter('#admin_login')->count() ) {
    $form = $crawler->selectButton('Log in')->form();
    $pageCrawler = $client->submit($form, ['admin_pass' => $admin['password1']]);
}
$step++;
print("Step $step: admin log in \n");

// [Step]
// set new settings for admins and moderators
$general_settings = $siteurl . '/index.php?action=admin;area=securitysettings;sa=general';
$crawler = $client->request('GET', $general_settings);
$form = $crawler->selectButton('Save')->form();
$pageCrawler = $client->submit($form, [
    'auto_admin_session' => '1',
    'securityDisable' => '1',
    'securityDisable_moderate' => '1',
]);
$step++;
print("Step $step: set new settings for admins and moderators \n");

// [Step]
// Avatar settings
$crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=manageattachments;sa=avatars');
$form = $crawler->selectButton('Save')->form();
$pageCrawler = $client->submit($form, [
    'avatar_max_width' => '120',
    'avatar_max_height' => '120',
]);
$step++;
print("Step $step: set new avatar settings for all users \n");

// [Step]
// HelpDisplay time taken to create every page
$crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=featuresettings;sa=layout');
$form = $crawler->selectButton('Save')->form();
$pageCrawler = $client->submit($form, [
    'timeLoadPageEnable' => '1',
]);
$step++;
print("Step $step: Enable show time taken to create every page \n");


// @TODO: http://localhost/elki/t1/index.php?action=admin;area=managesearch;sa=createmsgindex


// [Step]
// Maximum allowed post size
$crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=postsettings;sa=posts');
$form = $crawler->selectButton('Save')->form();
$pageCrawler = $client->submit($form, [
    'max_messageLength' => '65535',
]);
$step++;
print("Step $step: Maximum allowed post size set 65535 \n");

// [Step]
// Upload avatar for admin-user
$crawler = $client->request('GET', $siteurl . '/index.php?action=profile;area=forumprofile');
$form = $crawler->selectButton('Change profile')->form();
$pageCrawler = $client->submit($form, [
    'avatar_choice' => 'upload',
    'attachment' => __DIR__ . '/homer-simpson.jpg',
    'gender' => $admin['gender'],
]);
// $fields = array("user" => "test");
// $fields["file"] = fopen('/path/to/file', 'rb');
// $this->client->request("POST", $url, array('Content-Type => multipart/form-data'), array(), array(), $fields);
$step++;
print("Step $step: сhange profile settings for admin-user \n");

// [Step]
// Create first message
$crawler = $client->request('GET', $siteurl . '/index.php?action=post;topic=1.0');
$form = $crawler->selectButton('Post')->form();
try {
    $pageCrawler = $client->submit($form, [
        'message' => 'Hello, all!',
        'attachment' => [__DIR__ . '/cat.jpg'],
    ]);
} catch (\InvalidArgumentException $e) {
    dump($e);
    die;
}
$step++;
print("Step $step: Сreate new message with attachment image \n");

// for elk < 1.0.8
if (file_exists($extractdir . '/Settings.php') and preg_match('/@version (\d+.\d+.\d+)/', file_get_contents($extractdir . '/Settings.php'), $m)) {
    $elkversion = $m[1];
    if (version_compare($elkversion, '1.0.8', '<')) {
        fixdberror($extractdir, $db);
        $step++;
        print("Step $step: fix db error \n");
    }
}

createDemoBoards($demoboards, $client);

// [Step]
$step++;
print("Step $step: install fancybox addon: ");
$crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=packages;sa=servers');
$form = $crawler->selectButton('Download')->form();
$pageCrawler = $client->submit($form, [
    'package' => $config['fancyboxurl'],
]);

$pageCrawler->filter('p.infobox')->each(function($node) {
    print $node->text()."\n";
});

$crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=packages;sa=install;package=Elk_FancyBox.zip');
$form = $crawler->selectButton('Install now')->form();
$pageCrawler = $client->submit($form, []);

// Fancybox addon: set settings
$crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=addonsettings;sa=fancybox');
$form = $crawler->selectButton('Save')->form([
    'fancybox_enabled' => '1'
]);
$pageCrawler = $client->submit($form, []);

