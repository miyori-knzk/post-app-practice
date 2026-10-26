# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 概要

投稿を一覧・編集・削除できる小さな Laravel 10 アプリ（Tutorial 13〜15 の題材）。認証は Laravel Fortify、実行環境は Laravel Sail（Docker）＋ MySQL。ドキュメントやコミットメッセージは日本語で書く（コミットは `13-2: ...` のように Tutorial 番号を接頭辞にする慣習）。

## コマンド

すべて Sail 経由で実行する（ホストに PHP/MySQL は前提にしない）。

```bash
./vendor/bin/sail up -d                      # 起動
./vendor/bin/sail down                       # 停止
./vendor/bin/sail artisan migrate --seed     # テーブル作成＋練習用データ
./vendor/bin/sail artisan migrate:fresh --seed  # DB を作り直す
./vendor/bin/sail artisan test               # 全テスト
./vendor/bin/sail artisan test --filter=テスト名   # 単一テスト
./vendor/bin/sail pint                       # コード整形（Laravel Pint）
./vendor/bin/sail npm run dev                # Vite
```

初回の `migrate` で「Connection refused」が出たら MySQL の起動待ちなので、少し待って再実行する。テストは `phpunit.xml` により MySQL の `testing` データベースを使う。

## アーキテクチャ

- **ルート**: `routes/web.php`。`/posts` 系（index / create / store / show / edit / update / destroy）、リプライの送信（`POST /posts/{post}/replies`）と削除（`DELETE /replies/{reply}`）はすべて `auth` ミドルウェア内。`/posts/create` は `/posts/{post}` に食われないよう `show` より前に定義している。新規作成（`store`）の入力チェックは `update` と共通の `PostController::validatedPost()`、投稿者は `$request->user()->posts()->create()` でログインユーザーに固定（フォームから `user_id` は受け取らない）。
- **認可**: `PostController` の edit/update/destroy と `ReplyController` の destroy で `$this->authorize()` を呼び、`app/Policies/PostPolicy.php` / `ReplyPolicy.php` で「投稿者本人のみ」を判定（他人の投稿・リプライは 403）。ポストの投稿者でも他人のリプライは消せない。Policy は命名規約による自動検出で、`AuthServiceProvider::$policies` には登録していない。ビュー側でも編集・削除ボタンを本人にだけ出している（詳細表示・リプライ送信はログイン済みなら誰でも可）。
- **認証**: Fortify。ログイン・登録画面は `FortifyServiceProvider` で `resources/views/auth/*` を指定。ユーザー作成などの処理は `app/Actions/Fortify/`。
- **モデル**: `User` 1—n `Post` n—1 `Category`。`Reply` は `User` と `Post` に n—1（ポスト削除でリプライも cascade 削除）。一覧は `Post::with(['user', 'category'])` で eager load。詳細画面（`posts/show.blade.php`）はポストとリプライを古い順に表示し、本文は `Post::CONTENT_MAX_LENGTH`（140字）以内。
- **シーダー**: `DatabaseSeeder` がカテゴリ 3 件、練習用ユーザー 7 人（検証用は `usera@example.com` / `userb@example.com`、全員パスワード `password`）、ポスト 25 件、リプライ 5 件を入れる。リプライの時刻は返信先ポストの何時間後かで指定する。

## ディレクトリ

- `docs/` — 学習者が自分で作る設計書（Tutorial 番号ごとのフォルダ、Markdown と draw.io / PNG）。
- `docs/reply.md` — リプライ機能の設計書（Issue #1）。実装とテストはこの設計に沿っている。
- `answers/` — Tutorial 13 の設計書の解答例。アプリのコードとは無関係（詳細は `answers/README.md`）。
