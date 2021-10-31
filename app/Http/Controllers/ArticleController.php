<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class ArticleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $method = 'GET';
        $tag_id = 'PHP';
        $per_page = 30;

        $url = config('qiita.url') . '/api/v2/tags/' . $tag_id . '/items?per_page=' . $per_page;
        // $optionsにトークンを指定
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ],
        ];
        // Client(接続する為のクラス)を生成
        $client = new Client();
        // try catchでエラー時の処理を書く
        try {
            // データを取得し、JSON形式からPHPの変数に変換
            $response = $client->request($method, $url, $options);
            $body = $response->getBody();
            $articles = json_decode($body, false);
        } catch (\Throwable $th) {
            $articles = null;
        }

        // ここから自分記事取得

        $method = 'GET';
        $per_page = 30;

        $url = config('qiita.url') . '/api/v2/authenticated_user/items?per_page=' . $per_page;
        // $optionsにトークンを指定
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ],
        ];
        // Client(接続する為のクラス)を生成
        $client = new Client();
        // try catchでエラー時の処理を書く
        try {
            // データを取得し、JSON形式からPHPの変数に変換
            $response = $client->request($method, $url, $options);
            $body = $response->getBody();
            $my_articles = json_decode($body, false);
        } catch (\Throwable $th) {
            $my_articles = null;
        }
        return view('articles.index')->with(compact('articles', 'my_articles'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('articles.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $method = 'POST';

        $url = config('qiita.url') . '/api/v2/items';
        $tag_array = explode(' ', $request->tags);
        $tags = array_map(function ($tag) {
            return ['name' => $tag];
        }, $tag_array);

        $data = [
            'title' => $request->title,
            'body' => $request->body,
            'private' => $request->private == "true" ? true : false,
            'tags' => $tags
        ];

        // $optionsにトークンを指定
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
                'Content-Type' => 'application/json',
            ],
            'json' => $data,
        ];

        // Client(接続する為のクラス)を生成
        $client = new Client();

        // try catchでエラー時の処理を書く
        try {
            // データを取得し、JSON形式からPHPの変数に変換
            $response = $client->request($method, $url, $options);
            $body = $response->getBody();
            $article = json_decode($body, false);
        } catch (\GuzzleHttp\Exception\ClientException $e) {

            // return back()->withErrors(['error' => $e->getResponse()->getReasonPhrase()]);
            return back()->withErrors(['error' =>  "<a href='https://qiita.com/private/{{$article->id}}'>記事</a>投稿に失敗しました！"]);
        }
        $message = new \Illuminate\Support\HtmlString('記事の投稿に成功しました');
        return redirect()->route('articles.index')->with('flash_message', $message);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $method = 'GET';

        $url = config('qiita.url') . '/api/v2/items/' . $id;
        // $optionsにトークンを指定
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ],
        ];
        // Client(接続する為のクラス)を生成
        $client = new Client();
        // try catchでエラー時の処理を書く
        try {
            // データを取得し、JSON形式からPHPの変数に変換
            $response = $client->request($method, $url, $options);
            $body = $response->getBody();
            $article = json_decode($body, false);

            $parser = new \cebe\markdown\GithubMarkdown();
            $parser->KeepListStartNumber = true;
            $parser->enableNewlines = true;

            $html_string = $parser->parse($article->body);
            $article->html = new \Illuminate\Support\HtmlString($html_string);
        } catch (\Throwable $th) {
            return back();
        }
        // 自分の記事
        $method = 'GET';
        // QIITA_URLの値を取得してURLを定義
        $url = config('qiita.url') . '/api/v2/authenticated_user/';
        // $optionsにトークンを指定
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ],
        ];
        // Client(接続する為のクラス)を生成
        $client = new Client();
        try {
            // データを取得し、JSON形式からPHPの変数に変換
            $response = $client->request($method, $url, $options);
            $body = $response->getBody();
            $user = json_decode($body, false);
        } catch (\Throwable $th) {
            return back();
        }
        return view('articles.show')->with(compact('article', 'user'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $method = 'GET';

        $url = config('qiita.url') . '/api/v2/items/' . $id;
        // $optionsにトークンを指定
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ],
        ];
        // Client(接続する為のクラス)を生成
        $client = new Client();
        try {
            // データを取得し、JSON形式からPHPの変数に変換
            $response = $client->request($method, $url, $options);
            $body = $response->getBody();
            $article = json_decode($body, false);

            // tagsを配列からスペース区切りに変換
            $tag_array = array_map(function ($tag) {
                return $tag->name;
            }, $article->tags);
            $article->tags = implode(' ', $tag_array);
        } catch (\Throwable $th) {
            return back();
        }
        return view('articles.edit')->with(compact('article'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $method = 'PATCH';

        $url = config('qiita.url') . '/api/v2/items/' . $id;

        $tag_array = explode(' ', $request->tags);
        $tags = array_map(function ($tag) {
            return ['name' => $tag];
        }, $tag_array);

        $data = [
            'title' => $request->title,
            'body' => $request->body,
            'private' => $request->private == "true" ? true : false,
            'tags' => $tags
        ];

        // $optionsにトークンを指定
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
                'Content-Type' => 'application/json',
            ],
            'json' => $data,
        ];

        // Client(接続する為のクラス)を生成
        $client = new Client();
        try {
            // データを取得し、JSON形式からPHPの変数に変換
            $response = $client->request($method, $url, $options);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return back()->withErrors(['error' => $e->getResponse()->getReasonPhrase()]);
        }
        return redirect()->route('articles.index')->with('flash_message', '記事の更新に成功しました。');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $method = 'DELETE';

        $url = config('qiita.url') . '/api/v2/items/' . $id;

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
            ],
        ];
        $client = new Client();

        try {
            $response = $client->request($method, $url, $options);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return back()->withErrors(['error' => $e->getResponse()->getReasonPhrase()]);
        }
        return redirect()->route('articles.index')->with('flash_message', '記事を削除しました');;
    }

    protected function getToken ()
    {
        return Crypt::decryptString(Auth::user()->token);
    }
}

