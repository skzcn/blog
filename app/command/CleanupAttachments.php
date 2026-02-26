<?php
declare (strict_types = 1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class CleanupAttachments extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('cleanup:attachments')
            ->setDescription('清理未使用的附件（已上传但超过2小时未发布的图片和视频）');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('<info>开始清理未使用的附件...</info>');

        // 查找状态为 0 且超过 2 小时的附件
        $threshold = time() - 7200;
        $orphans = Db::name('attachment')
            ->where('status', 0)
            ->where('create_time', '<', $threshold)
            ->select();

        if ($orphans->isEmpty()) {
            $output->writeln('<comment>没有发现需要清理的附件。</comment>');
            return;
        }

        $deletedCount = 0;
        $basePath = public_path();

        foreach ($orphans as $item) {
            $url = $item['url'];
            // 构造磁盘绝对路径
            $filePath = $basePath . str_replace('/', DIRECTORY_SEPARATOR, ltrim($url, '/'));

            if (file_exists($filePath)) {
                if (@unlink($filePath)) {
                    $output->writeln("<info>已删除文件: {$url}</info>");
                } else {
                    $output->writeln("<error>删除文件失败: {$url}</error>");
                }
            } else {
                $output->writeln("<comment>文件不存在: {$url}</comment>");
            }

            // 从数据库删除记录
            Db::name('attachment')->delete($item['id']);
            $deletedCount++;
        }

        $output->writeln("<info>清理完成，共清理了 {$deletedCount} 条记录。</info>");
    }
}
