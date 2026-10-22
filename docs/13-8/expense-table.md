# requests

| カラム名          | データ型  | 制約                        | 説明                                   |
| :---------------- | :-------- | :-------------------------- | :------------------------------------- |
| 　id              | BIGINT    | PRIMARY KEY, AUTO_INCREMENT | 申請を1件ずつ区別する番号              |
| 　user_id         | BIGINT    | NOT NULL, FOREIGN KEY       | 誰の申請か                             |
| 　date            | DATE      | NOT NULL                    | 立替日                                 |
| 　amount          | INT(5)    | NOT NULL                    | 立て替えた金額                         |
| 　purpose         | STRING    | NOT NULL                    | 使途                                   |
| 　category        | TINYINT   | NOT NULL                    | 1:交通費　2:書籍代　3:会議費　4:その他 |
| 　approvel_status | TINYINT   | NOT NULL                    | 1:承認待ち　2:承認済み　3:差し戻し     |
| 　reason          | STRING    | -                           | 差し戻しの際は必須                     |
| 　created_at      | TIMESTANP | -                           | 登録した日時                           |
| 　updated_at      | TIMESTANP | -                           | 最後に書き換えた日時                   |

# users

| カラム名     | データ型  | 制約                        | 説明                          |
| :----------- | :-------- | :-------------------------- | :---------------------------- |
| 　id         | BIGINT    | PRIMARY KEY, AUTO_INCREMENT | ユーザーを1人ずつ区別する番号 |
| 　name       | STRING    | NOT NULL                    | 名前                          |
| 　role       | TINYINT   | -                           | 1:承認者                      |
| 　created_at | TIMESTANP | -                           | 登録した日時                  |
| 　updated_at | TIMESTANP | -                           | 最後に書き換えた日時          |
