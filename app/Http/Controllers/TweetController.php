<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use PHPHtmlParser\Dom;
use Sunra\PhpSimple\HtmlDomParser;

class TweetController extends BaseController
{
    public function getHtml($count)
    {
        return $this->handle($count, 'html');
    }

    public function getPlain($count)
    {
        return $this->handle($count, 'plain');
    }

    private function handle($count, $option)
    {
        $key = "tweet.$option.$count";
        if (Cache::has($key)) return response(Cache::get($key))->header('Content-Type', 'application/json')->header('Access-Control-Allow-Origin', '*');
        $rep = file_get_contents("http://t.kcwiki.moe/?json=1&count=$count");
        if ($rep) {
            $result = json_decode($rep, true);
            $posts = $result['posts'];
            $output = [];
            foreach ($posts as $post) {
                $dom = new Dom;
                $dom->load($post['content']);
                $p = $dom->find('p');
                $plength = count($p);
                $new_post = [];
                $new_post['jp'] = $p[0]->outerHtml;
                $new_post['zh'] = '';
                for ($i=1; $i < $plength; $i++) {
                    $new_post['zh'] .= $p[$i]->outerHtml;
                }
                $new_post['date'] = $post['date'];
                if ($option == 'plain') {
                    $new_post['zh'] = HtmlDomParser::str_get_html($new_post['zh'])->plaintext;
                    $new_post['jp'] =  HtmlDomParser::str_get_html($new_post['jp'])->plaintext;
                }
                array_push($output, $new_post);
            }
            Cache::put($key, $output, 5);
            return response($output)->header('Content-Type', 'application/json')->header('Access-Control-Allow-Origin', '*');
        } else {
            return response()->json(['result' => 'error', 'reason' => 'Getting tweets failed.']);
        }
    }

}