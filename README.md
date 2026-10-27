# post-app

投稿を一覧・編集・削除できる、小さな Laravel アプリです。Tutorial 13 から 15 まで、このアプリ 1 本を題材に設計・実装・仕組み化を進めます。

## できること

- ユーザー登録・ログイン・ログアウト
- 投稿の一覧表示（投稿者の名前とカテゴリつき）
- 投稿の編集（タイトル・カテゴリ・本文）と削除
- 自分の投稿だけを編集・削除（他人の投稿は編集・削除ボタンが出ず、URL を直接開くと 403 になります）

## フォルダについて

`answers/` は Tutorial 13 の答え合わせ用で、アプリのコードとは関係ありません。設計書の解答例が入っているだけなので、アプリの動きには影響しません。詳しくは `answers/README.md` を見てください。

自分で作る設計書は `docs/` に置きます（Tutorial 13 の中で作ります）。

## 使用技術

| 項目           | 内容                   |
| :------------- | :--------------------- |
| フレームワーク | Laravel 10             |
| 言語           | PHP                    |
| データベース   | MySQL                  |
| 実行環境       | Laravel Sail（Docker） |
| 認証           | Laravel Fortify        |

PHP と MySQL のバージョンは Sail のコンテナが決めます。手元で確かめるときは `./vendor/bin/sail php -v` と `./vendor/bin/sail mysql --version` を実行してください。

## セットアップ

Docker Desktop（または Docker Engine）を起動してから実行してください。Windows の方は WSL（Ubuntu）のターミナルで実行します。

```bash
# パッケージをインストールする
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install

# 環境ファイルを作る
cp .env.example .env

# Sail を起動する
./vendor/bin/sail up -d

# アプリケーションキーを作る
./vendor/bin/sail artisan key:generate

# テーブルを作り、練習用データを入れる
./vendor/bin/sail artisan migrate --seed
```

ブラウザで `http://localhost` を開くと、トップページが表示されます。

初回起動時は `migrate` で「Connection refused」が出ることがあります。MySQL の起動が終わっていないだけなので、少し待ってからもう一度実行してください。

## 練習用のアカウント

`migrate --seed` で、カテゴリ 3 件（お知らせ・技術メモ・雑記）と、次の 2 人、それぞれの投稿 2 件（計 4 件）が入ります。

| メールアドレス      | パスワード | 投稿                   |
| :------------------ | :--------- | :--------------------- |
| `usera@example.com` | `password` | `/posts/1`・`/posts/2` |
| `userb@example.com` | `password` | `/posts/3`・`/posts/4` |

本物の値は入っていません。すべて練習用のダミーです。

## テスト

```bash
./vendor/bin/sail artisan test
```

## 停止

```bash
./vendor/bin/sail down
```

このリポジトリは Tutorial 14 でも使います

## 設計のメモ

リプライ機能を「なぜそう作ったか」のメモです。

### 作らないと決めたもの

- リプライの編集、リプライへのリプライ（入れ子）、通知。今回は対象外にした
- ポストの投稿者が他人のリプライを消す機能。消せるのはリプライの投稿者本人だけ
- 一覧へのリプライ件数の表示。一覧への変更は「返信」リンクの追加だけにした
- 返信フォームの残り字数の表示。編集画面にはあるが、リプライでは必須にしなかった
- `ReplyPolicy` の `update`。編集をやらないので作っていない

### 選ばなかった作り方

- 一覧にフォームを埋め込まず、ポスト詳細画面（`posts.show`）を別に作った。一覧の動きを変えないため
- `PostPolicy` に足さず、`ReplyPolicy` を別に作った。`PostPolicy` を変えないため
- Policy を `AuthServiceProvider::$policies` に登録せず、命名規約による自動検出に任せた。既存の方針に揃えた
- 140字の上限を DB の制約にせず、アプリ側の検証で守る。`posts.content` と同じ作りにした
- リプライ用の文字数の定数を作らず、`Post::CONTENT_MAX_LENGTH` を使い回した
- `post_id` の外部キーのほかにインデックスを足さなかった。ポスト単位に古い順で読むだけなので足りる
- 返信先のポストは URL の `{post}`、返信者はログインユーザーで決め、フォームから受け取らない

### 知らずに変えると壊れる決めごと

- 一覧の並び（新しい順）・表示項目・eager load（`user` / `category`）、編集、削除の動きは変えない。リプライがあってもポストは削除できる
- `replies.post_id` の外部キーは `cascadeOnDelete`。外すとポストを消したときにリプライが残るか、削除が失敗する
- 本文の上限は `Post::CONTENT_MAX_LENGTH` を共有している。値を変えるとポストとリプライの両方に効く
- 字数は改行を1字として数えるため、検証の前に `\r\n` を `\n` に揃える。外すと改行つきの140字がエラーになる
- 本文のエラーメッセージは `PostController::update` と同じ文言・同じ作り方にしてある。片方だけ直すとずれる
- 認可は `ReplyPolicy::delete`（`$user->id === $reply->user_id`）に集約している。ビューの `@can('delete', $reply)` と、コントローラの `authorize` の両方で使う
- `ReplyPolicy` は命名規約で見つけている。クラス名やモデル名を変えると効かなくなる

### 設計書のありか

- [`docs/reply.md`](docs/reply.md) — リプライ機能の設計書（元の Issue は #1）。実装した時点の記録で、そのあとは更新していない
