<?php

chdir(__DIR__);

define( 'CREATE_TIME', time() );
const DEST_DIR = __DIR__;

require_once __DIR__ . '/vendor/autoload.php';

use \Symfony\Component\Yaml\Yaml;

// http://victor.4devs.io/en/web-scraping/scraping-data-with-goutte.html
// http://symfony.com/doc/current/components/dom_crawler.html
// http://docs.guzzlephp.org/en/latest/quickstart.html
// http://onedev.net/post/417
use \Goutte\Client;

global $mysqli, $db;

$zf = DEST_DIR . '/ElkArte_v1-1-beta4_install.zip';
$url_zf = 'https://github.com/elkarte/Elkarte/releases/download/v1.1.0-beta.4/ElkArte_v1-1-beta4_install.zip';
// $url_zf_sha1 = 'df3d4b5f4c84d86a7f52816cf33509d4681e842a';
// $url_zf_sha1 = 'b4cf004897a3e3df15c13aed92053255465a66de';
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

$extractdir = !empty($extractdir) ? $extractdir : DEST_DIR . '/t1';
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

function get_db($dbsettings)
{
    static $load_db = null;

    if (!is_null($load_db)) {
        return false;
    }

    if ('mysql' === $dbsettings['db_type']) {
        $mysqli = new mysqli($dbsettings['db_server'], $dbsettings['db_user'], $dbsettings["db_passwd"], $dbsettings["db_name"]);
        $load_db = true;

        if (mysqli_connect_errno()) {
            printf("Подключение не удалось: %s\n", mysqli_connect_error());
            exit();
        }

        return $mysqli;
    }
}

function is_dir_empty($dir)
{
    if (!is_readable($dir)) {
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

    $bid = 0;
    foreach ($boards as $board => $childs) {
        // sleep(3);

        if ( ! $bid ) {
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


        // $link = $pageCrawler->selectLink($board)->link()->getUri();
        // preg_match('~board=(\d+)~iu', $link, $matches);
        // $bid = $matches[1];
        $bid = countBoards();

        // dump($pageCrawler);

        if (!empty($childs)) {
            foreach ($childs as $b) {
                // sleep(3);
                $crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=manageboards;sa=newboard;cat=1');
                $form = $crawler->selectButton('Add Board')->form([
                    'board_name' => $b,
                    'desc' => '',
                    'placement' => 'child',
                    'board_order' => $bid,
                ]);

                $pageCrawler = $client->submit($form, []);
                print("Create $b child of $board.\n");

                /*
                $link2 = $pageCrawler->selectLink($b)->link()->getUri();
                preg_match('~board=(\d+)~iu', $link2, $matches2);

                if (!isset($matches2[1])) {
                    file_put_contents(__DIR__.'/temp-test/error-board.html', $pageCrawler->html());
                    continue;
                }
                $bid2 = $matches2[1];
                // echo $b, ' ', $link2, ' ', $bid2, "\n";
                */
            }
        }
    }
}

function createDemoPost($idboard, Client $client)
{
    global $scripturl, $siteurl;
    
    sleep(3);

    // https://github.com/fzaninotto/Faker
    $faker = Faker\Factory::create();

    $crawler = $client->request('GET', $siteurl . '/index.php?action=post;board=' . intval($idboard) . '.0');
    $form = $crawler->selectButton('Post')->form();
    $s = substr($faker->text, 0, 70);
    $post = $faker->text;
    try {
        $pageCrawler = $client->submit($form, [
            'subject' => $s,
            'message' => $post,
            // 'attachment' => [__DIR__ . '/cat.jpg'],
        ]);

        if ($pageCrawler->filter('#post_error_list')->count()) {
            $pageCrawler->filter('#post_error_list li')->each(function($node) {
                echo 'Error! ', $node->text(), "\n";
                file_put_contents(__DIR__.'/temp-test/post-' . $s . '.html', $pageCrawler->html());
            });
            die;
        }

        if ($pageCrawler->filter('#attach_generic_error_list')->count()) {
            $pageCrawler->filter('#attach_generic_error_list li')->each(function($node) {
                echo 'Error! ', $node->text(), "\n";
                file_put_contents(__DIR__.'/temp-test/post-' . $s . '.html', $pageCrawler->html());
            });
            die;
        }

        if ($pageCrawler->filter('#fatal_error')->count()) {
            $pageCrawler->filter('#fatal_error div.errorbox')->each(function($node) {
                echo 'Error! ', $node->text(), "\n";
                file_put_contents(__DIR__.'/temp-test/post-' . $s . '.html', $pageCrawler->html());
            });
            die;
        }

    } catch (\InvalidArgumentException $e) {
        dump($e);
        die;
    }
    finally {
        echo "Create new post: ", substr($s, 0, 10), "... \n";
    }
}

function countBoards()
{
    global $mysqli, $db;

    $result = $mysqli->query('SELECT COUNT(*) FROM '.$db['db_prefix'].'boards LIMIT 1');
    $total = 0;
    if ($result) {
        $total = $result->fetch_row()[0];
        $result->free();
    }
    //$mysqli->close();

    return $total;
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

function installFancyboxAddon($client, $siteurl, $config)
{
	// $crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=packages;sa=servers');
	// $form = $crawler->selectButton('Download')->form();
	// $pageCrawler = $client->submit($form, [
		// 'package' => $config['fancyboxurl'],
	// ]);
	$crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=packages;sa=upload');
	$form = $crawler->selectButton('Upload')->form();
	$pageCrawler = $client->submit($form, [
		'package' => $config['fancyboxfile'],
	]);

	$pageCrawler->filter('p.infobox')->each(function($node) {
		print $node->text()."\n";
	});

	// $crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=packages;sa=install;package=Elk_FancyBox.zip');
	$crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=packages;sa=install;ve=1.0;package=Elk_FancyBox-master.zip'); // emulating mode for v1.1
	$form = $crawler->selectButton('Install now')->form();
	$pageCrawler = $client->submit($form, []);

	// Fancybox addon: set settings
	$crawler = $client->request('GET', $siteurl . '/index.php?action=admin;area=addonsettings;sa=fancybox');
	$form = $crawler->selectButton('Save')->form([
		'fancybox_enabled' => '1'
	]);
	$pageCrawler = $client->submit($form, []);
}

// [Step]
$step++;
print("Step $step: install fancybox addon: ");
installFancyboxAddon($client, $siteurl, $config);


