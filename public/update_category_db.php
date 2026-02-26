<?php
require 'public/index.php';
use think\facade\Db;

try {
    // Add image column if not exists
    $stmt = Db::query("SHOW COLUMNS FROM blog_category LIKE 'image'");
    if (empty($stmt)) {
        echo "Adding 'image' column to blog_category...\n";
        Db::execute("ALTER TABLE blog_category ADD COLUMN image VARCHAR(255) DEFAULT '' COMMENT '背景图片' AFTER icon");
    } else {
        echo "'image' column already exists.\n";
    }
    echo "Success!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
