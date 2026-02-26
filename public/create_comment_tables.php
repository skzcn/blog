<?php
// public/create_comment_tables.php
namespace think;
require __DIR__ . '/../vendor/autoload.php';

$app = new App();
$http = $app->http;
$response = $http->run();

use think\facade\Db;

try {
    $sql1 = "CREATE TABLE IF NOT EXISTS `blog_comment` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `article_id` int(11) NOT NULL COMMENT '文章ID',
      `user_id` int(11) NOT NULL COMMENT '用户ID',
      `content` text NOT NULL COMMENT '评论内容',
      `status` tinyint(2) NOT NULL DEFAULT '1' COMMENT '状态 1:正常 0:隐藏',
      `create_time` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_article` (`article_id`),
      KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论表';";

    $sql2 = "CREATE TABLE IF NOT EXISTS `blog_sensitive_words` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `word` varchar(255) NOT NULL COMMENT '敏感词',
      `create_time` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='敏感词表';";

    Db::execute($sql1);
    Db::execute($sql2);

    echo "Tables created successfully.";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
