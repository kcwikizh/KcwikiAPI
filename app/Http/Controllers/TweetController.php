<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use PHPHtmlParser\Dom;

class TweetController extends BaseController
{
    public function getHtml($count)
    {
        return $this->handleCount($count, 'html');
    }

    public function getExtracted($count)
    {
        return $this->handleCount($count, 'extracted');
    }

    public function getPlain($count)
    {
        return $this->handleCount($count, 'plain');
    }

    public function getDatePosts($date)
    {
        return $this->handleDate($date, 'extracted');
    }

    public function getInfo()
    {
        if (Cache::tags('tweet')->has('info'))
            return response(Cache::tags('tweet')->get('info'))->header('Content-Type', 'application/json')->header('Access-Control-Allow-Origin', '*');
        $raw = file_get_contents('https://static.kcwiki.moe/Avatar/archives.json');
        $avatars = json_decode($raw, true);
        if (Storage::disk('local')->has('twitter.info.json')) {
            $info = json_decode(Storage::disk('local')->get('twitter.info.json'), true);
        } else {
            $info = array('name' => '「艦これ」開発/運営');
        }
        $base = 'http://static.kcwiki.moe/Avatar/';
        $avatars = array_map(function($url) use ($base) { return $base.$url;}, $avatars);
        return response()->json([
            'name' => $info['name'],
            'avatars' => $avatars
        ]);

    }

    private function handleCount($count, $option)
    {
        if (isset($_GET['until'])) {
            $until = intval($_GET['until']);
            $key = "tweet.$option.$count.$until";
            $url = "https://t.kcwiki.moe/?json=flow.get&count=$count&until=$until";
        } else {
            $key = "tweet.$option.$count";
            $url = "https://t.kcwiki.moe/?json=1&count=$count";
        }
        return $this->handle($key, $url, $option);
    }

    private function handleDate($date, $option)
    {
        $key = "tweet.$option.$date";
        $url = "https://t.kcwiki.moe/api/get_date_posts/?date=$date&count=99";
        return $this->handle($key, $url, $option);
    }

    private function handle($key, $url, $option)
    {
        $tag = "tweet";
        if (Cache::tags($tag)->has($key)) return response(Cache::tags($tag)->get($key))->header('Content-Type', 'application/json')->header('Access-Control-Allow-Origin', '*');
        $rep = file_get_contents($url);
        if ($rep) {
            $result = json_decode($rep, true);
            $posts = $result['posts'];
            $output = [];
            foreach ($posts as $post) {
                $dom = new Dom;
                $dom->load($post['content']);
                $new_post = [];
                if (array_key_exists('ozh_ta_id', $post['custom_fields']) && is_array($post['custom_fields']['ozh_ta_id']))
                    $new_post['id'] = $post['id'];
                else
                    $new_post['id'] = '';
                $img = $dom->find('img');
                if (count($img) > 0 && $option != 'html') {
                    $new_post['img'] = $img[0]->getAttribute('src');
                    foreach ($img as $x) {
                        $parent = $x->getParent();
                        $parentTagName = $parent->getTag()->name();
                        if ($parentTagName == 'a') {
                            $parent->delete();
                        } else {
                            $x->delete();
                        }
                    }
                } else if ($option != 'html') {
                    $new_post['img'] = '';
                }
                $p = $dom->find('p, div');
                $new_post['jp'] = '';
                $new_post['zh'] = '';
                $n = $this->detect($p);
                for ($i=0; $i <= $n; $i++) {
                    $new_post['jp'] .= $p[$i]->innerHtml;
                }
                for ($i=$n+1; $i < count($p); $i++) {
                    $new_post['zh'] .= $p[$i]->innerHtml;
                }
                $new_post['date'] = $post['date'];
                if ($option == 'plain') {
                    $new_post['zh'] = strip_tags($this->expandUrl($new_post['zh']));
                    $new_post['jp'] = strip_tags($this->expandUrl($new_post['jp']));
                }
                array_push($output, $new_post);
            }
            Cache::tags($tag)->put($key, $output, 5);
            return response($output)->header('Content-Type', 'application/json')->header('Access-Control-Allow-Origin', '*');
        } else {
            return response()->json(['result' => 'error', 'reason' => 'Getting tweets failed.']);
        }
    }

    private function expandUrl($html) {
        return str_replace('http://','',preg_replace('/<a[^>]*?href\s*=\s*"([^"]*?)"[^>]*?>[^こ<]*?<\/a>/', '\1', $html));
    }

    private function detect($paragraphs) {
        for ($i=0; $i < count($paragraphs); $i++) {
            $p = $paragraphs[$i]->innerHtml;
            $text = strip_tags($p);
            if (preg_match('/#艦これ/', $text)) {
                return $i;
            }
        }
        return 0;
    }
}
