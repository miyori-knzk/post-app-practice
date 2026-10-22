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

- **ルート**: `routes/web.php`。`/posts` 系（index / edit / update / destroy）はすべて `auth` ミドルウェア内。投稿の新規作成・詳細表示ルートはまだない。
- **認可**: `PostController` の edit/update/destroy で `$this->authorize()` を呼び、`app/Policies/PostPolicy.php` で「投稿者本人のみ」を判定（他人の投稿は 403）。Policy は命名規約による自動検出で、`AuthServiceProvider::$policies` には登録していない。ビュー側でも編集・削除ボタンを本人にだけ出している。
- **認証**: Fortify。ログイン・登録画面は `FortifyServiceProvider` で `resources/views/auth/*` を指定。ユーザー作成などの処理は `app/Actions/Fortify/`。
- **モデル**: `User` 1—n `Post` n—1 `Category`。一覧は `Post::with(['user', 'category'])` で eager load。
- **シーダー**: `DatabaseSeeder` がカテゴリ 3 件と練習用ユーザー 2 人（`usera@example.com` / `userb@example.com`、パスワード `password`）、各 2 件の投稿を入れる。

## ディレクトリ

- `docs/` — 学習者が自分で作る設計書（Tutorial 番号ごとのフォルダ、Markdown と draw.io / PNG）。
- `answers/` — Tutorial 13 の設計書の解答例。アプリのコードとは無関係（詳細は `answers/README.md`）。
