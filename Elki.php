<?php

chdir(__DIR__);

define( 't', time() );
const d = __DIR__;

require_once __DIR__ . '/vendor/autoload.php';

use \Symfony\Component\Yaml\Yaml;

// http://victor.4devs.io/en/web-scraping/scraping-data-with-goutte.html
// http://symfony.com/doc/current/components/dom_crawler.html
// http://onedev.net/post/417
use Goutte\Client;

$zf = d . '/ElkArte_v1-0-7_install.zip';
$url_zf = 'http://github.com/elkarte/Elkarte/releases/download/v1.0.7/ElkArte_v1-0-7_install.zip';
$url_zf_sha1 = 'B1CF32F1C633AA6F4031B7D487459ECEF1E3750C';
$extractdir = d . '/t1';
$url = 'http://localhost/elki/t1/install.php';
$siteurl = 'http://localhost/elki/t1';

$config = Yaml::parse(file_get_contents('config.yml'));
$db = array_map(function($a){ return str_replace('{{t}}', t, $a); }, $config['db']);
$admin = $config['admin'];


function is_dir_empty($dir)
{
    if (!is_readable($dir)) {
        return null;
    }

    return (count(scandir($dir)) === 2);
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

if ( ! file_exists($zf) ) {
    print("Download ElkArte\n");
    file_put_contents($zf, fopen($url_zf, 'r'));
    if (strtoupper(sha1_file($zf)) !== $url_zf_sha1) {
        echo strtoupper(sha1_file($zf));
        echo "\n" . $url_zf_sha1 . "\n";
        die('Bad file!');
    }
}

if ( ! is_dir($extractdir) || is_dir_empty($extractdir)) {
    $zip = new ZipArchive;
    $zip->open($zf);
    $zip->extractTo($extractdir);
    $zip->close();
}

$client = new Client();
$crawler = $client->request('GET', $url);
printStep($crawler);

// [step 2] Click #contbutt
// $link = $crawler->selectLink('#contbutt')->link();
// $crawler = $client->click($link);
$buttonCrawler = $crawler->selectButton('Continue');
$form = $buttonCrawler->form();
$pageCrawler = $client->submit($form);
printStep($pageCrawler);

// [step 3]
$buttonCrawler = $pageCrawler->selectButton('Continue');
$form = $buttonCrawler->form();
// $form = $crawler->filter('form')->first();
// print_r($form->getValues());
// print_r($form->all()['attributes']);
// print_r($form->getPhpValues());
$pageCrawler = $client->submit($form, $db);
printStep($pageCrawler);

// [step 4]
$buttonCrawler = $pageCrawler->selectButton('Continue');
$form = $buttonCrawler->form();
$pageCrawler = $client->submit($form, ['mbname' => 'Ёлки - Палки']);
printStep($pageCrawler);
$pageCrawler->filter('.panel ul li')->each(function ($node) {
    print $node->text()."\n";
});

// [step 5]
$buttonCrawler = $pageCrawler->selectButton('Continue');
$form = $buttonCrawler->form();
$pageCrawler = $client->submit($form, []);
printStep($pageCrawler);

// [step 6]
$buttonCrawler = $pageCrawler->selectButton('Continue');
$form = $buttonCrawler->form();
$pageCrawler = $client->submit($form, $admin);
printStep($pageCrawler);

// [step 7]
unlink($extractdir . '/install.php');

// Установка завершена.
// Перейдём к настройкам.

// [step 8]
// disable admin checks
$client = new Client();
$crawler = $client->request('GET', $siteurl);
$form = $crawler->selectButton('Log in')->form();
$pageCrawler = $client->submit($form, ['user' => $admin['username'], 'passwrd' => $admin['password1']]);
print("Step 8: success log in on site \n");

// [step 9]
// Admin confirm password
$crawler = $client->request('GET', $siteurl . '/index.php?action=admin');
if ( $crawler->filter('#admin_login')->count() ) {
    $form = $crawler->selectButton('Log in')->form();
    $pageCrawler = $client->submit($form, ['admin_pass' => $admin['password1']]);
}
print("Step 9: success admin log in \n");

// [step 10]
// set new settings for admins and moderators
$general_settings = $siteurl . '/index.php?action=admin;area=securitysettings;sa=general';
$crawler = $client->request('GET', $general_settings);
$form = $crawler->selectButton('Save')->form();
$pageCrawler = $client->submit($form, [
    'auto_admin_session' => '1',
    'securityDisable' => '1',
    'securityDisable_moderate' => '1',
]);
print("Step 10: success set new settings for admins and moderators \n");

// [step 11]
// Avatar settings
$crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=manageattachments;sa=avatars');
$form = $crawler->selectButton('Save')->form();
$pageCrawler = $client->submit($form, [
    'avatar_max_width' => '120',
    'avatar_max_height' => '120',
]);
print("Step 11: success set new avatar settings for all users \n");

// [step 12]
// Upload avatar for admin-user
$crawler = $client->request('GET', $siteurl . '/index.php?action=profile;area=forumprofile');
$form = $crawler->selectButton('Change profile')->form();
$pageCrawler = $client->submit($form, [
    'avatar_choice' => 'upload',
    'attachment' => __DIR__ . '/homer-simpson.jpg',
    // 'avatar_choice' => 'external',
    // 'userpicpersonal' => 'http://avki.ru/avatar-simpsons/avki-ru-0041-ava-simpson.gif',
    'gender' => '1' // 0 - Unknown, 1- Male, 2 - Female
]);
// $fields = array("user" => "test");
// $fields["file"] = fopen('/path/to/file', 'rb');
// $this->client->request("POST", $url, array('Content-Type => multipart/form-data'), array(), array(), $fields);
print("Step 12: success change profile settings for admin-user \n");

// [step 13]
// Create first message
$crawler = $client->request('GET', $siteurl . '/index.php?topic=1.0');
$form = $crawler->selectButton('Post')->form();
$pageCrawler = $client->submit($form, [
    'message' => 'Hello, all!',
]);
print("Step 13: success create new message \n");


