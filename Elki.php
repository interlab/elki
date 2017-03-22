<?php

chdir(__DIR__);

define( 'CREATE_TIME', time() );

require_once __DIR__ . '/vendor/autoload.php';

use \Symfony\Component\Yaml\Yaml;

// http://victor.4devs.io/en/web-scraping/scraping-data-with-goutte.html
// http://symfony.com/doc/current/components/dom_crawler.html
// http://docs.guzzlephp.org/en/latest/quickstart.html
// http://onedev.net/post/417
use \Goutte\Client;

global $mysqli, $db;

$zf = __DIR__ . '/ElkArte_v1-1-beta4_install.zip';
$url_zf = 'https://github.com/elkarte/Elkarte/releases/download/v1.1.0-beta.4/ElkArte_v1-1-beta4_install.zip';
$url_zf_sha1 = '31f84d24231bf076551b52ad841b5a1d06041757';

$use_custom_path = null;

// Extract directory
if (isset($argv[1])) {
    if (!is_dir($argv[1])) {
        $extractdir = mkdir($argv[1]) ? $argv[1] : null;
    } else {
        $extractdir = $argv[1];
    }
    $use_custom_path = true;
} else {
    $use_custom_path = false;
}

$extractdir = !empty($extractdir) ? $extractdir : __DIR__ . '/t1';
// echo "\r\n",'$extractdir    ', $extractdir, "\r\n";

// Site url
if ($use_custom_path && !isset($argv[2])) {
    throw new \Exception('Url param not found!');
}
$siteurl = isset($argv[2]) ? $argv[2] : 'http://localhost/elki1-1/t1';
$url = $siteurl . '/install/install.php'; // v1.1
// echo "\r\n",'url: ', $url, "\r\n";

$config = Yaml::parse(file_get_contents(__DIR__ . '/config.yml'));

$db = array_map(function($a){ return str_replace('{{t}}', CREATE_TIME, $a); }, $config['db']);
$admin = $config['admin'];
$demoboards = $config['demoboards'];

$step = 0;

require_once __DIR__ . '/funcs.php';

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
del_dir($extractdir . '/install/');
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

// [Step]
// Check extensions for attachments
$crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=manageattachments;sa=attachments');
$form = $crawler->selectButton('Save')->form();
$pageCrawler = $client->submit($form, [
    'attachmentCheckExtensions' => '1',
]);
$step++;
print("Step $step: Attachments: enable check extensions \n");

// [Step]
// Show attachments for guests
$crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=permissions;sa=modify;group=-1');
$form = $crawler->selectButton('Save changes')->form();
$pageCrawler = $client->submit($form, [
    'perm[board][view_attachments]' => 'on',
]);
$step++;
print("Step $step: Attachments: show for guests \n");

// @TODO: http://localhost/elki/t1/index.php?action=admin;area=managesearch;sa=createmsgindex

// echo "\r\n", __LINE__, "\r\n";

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
    'customfield[cust_gender]' => $admin['gender'], // v1.1
]);
// $fields = array("user" => "test");
// $fields["file"] = fopen('/path/to/file', 'rb');
// $this->client->request("POST", $url, array('Content-Type => multipart/form-data'), array(), array(), $fields);
$step++;
print("Step $step: change profile settings for admin-user \n");

// [Step]
// Create first message
$crawler = $client->request('GET', $siteurl . '/index.php?action=post;topic=1.0');
$form = $crawler->selectButton('Post')->form();
$m = 'Hello, all!

[html5audio]http://lubeh.matvey.ru/mp3/140.mp3[/html5audio]

[html5video]http://f.tiraspol.me/video/2013/12/24/orphans.webm[/html5video]

[html5video]http://simaru.tk/files/video/zubov.mp4[/html5video]

[html5video]http://tiraspol.me/files/html5test/bus.ogg[/html5video]';
try {
    $pageCrawler = $client->submit($form, [
        'message' => $m,
        'attachment' => [__DIR__ . '/cat.jpg'],
    ]);
} catch (\InvalidArgumentException $e) {
    dump($e);
    die;
}
$step++;
print("Step $step: Create new message with attachment image \n");

$mysqli = get_db($db);
// dump($mysqli);

createDemoBoards($demoboards, $client);
// die;

// [Step]
$step++;
print("Step $step: install fancybox addon: ");
installFancyboxAddon($client, $siteurl, $config);


