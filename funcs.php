<?php

use \Goutte\Client;

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
