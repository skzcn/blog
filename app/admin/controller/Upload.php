<?php
declare(strict_types=1);

namespace app\admin\controller;

use think\facade\Filesystem;
use think\exception\ValidateException;
use think\facade\Log;
use think\facade\Db;
use think\facade\Session;

/**
 * 文件上传控制器
 */
class Upload extends AdminBase
{
    /**
     * 上传图片（用于 wangEditor）
     */
    public function image()
    {
        // Suppress detailed errors for legacy compatibility (PHP 8.4+ Flysystem deprecation)
        error_reporting(E_ALL & ~E_DEPRECATED);

        try {
            $file = request()->file('file'); // wangEditor 默认参数名是 file
            if (!$file) {
                $file = request()->file('wangeditor-uploaded-image'); // 有时插件用这个
            }

            if (!$file) {
                return json(["errno" => 1, "message" => "未接收到文件，可能文件过大超出服务器限制"]);
            }

            // 验证扩展名和大小 (5MB)
            validate(['file' => 'fileExt:jpg,png,gif,jpeg,bmp,webp|fileSize:5242880']) 
                ->check(['file' => $file]);
            
            // 使用 public 磁盘，确保 public/storage 目录存在/可写
            // 若目录不存在，putFile 会尝试创建
            $savename = Filesystem::disk('public')->putFile('images', $file);
            
            if (!$savename) {
                 throw new \Exception("文件保存失败");
            }
            
            $url = '/storage/' . str_replace('\\', '/', $savename);

            // Record attachment
            $admin = Session::get('admin_user');
            Db::name('attachment')->insert([
                'url' => $url,
                'admin_id' => $admin['id'] ?? 0,
                'status' => 0,
                'type' => 'image',
                'create_time' => time()
            ]);

            return json([
                "errno" => 0,
                "data" => [
                    "url" => $url, 
                    "alt" => $file->getOriginalName(), 
                    "href" => $url
                ]
            ]);
        } catch (ValidateException $e) {
            return json(["errno" => 1, "message" => $e->getMessage()]);
        } catch (\Throwable $e) {
            // 捕获所有系统异常
            Log::error('Upload Error: ' . $e->getMessage());
            return json(["errno" => 1, "message" => "服务器错误: " . $e->getMessage()]);
        }
    }

    /**
     * 上传附件（用于 wangEditor 附件插件）
     */
    public function file()
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        try {
            $file = request()->file('file');
            if (!$file) {
                $file = request()->file('wangeditor-uploaded-attachment');
            }

            if (!$file) {
                 return json(["errno" => 1, "message" => "未接收到文件"]);
            }

            validate(['file' => 'fileSize:10485760']) // 10MB
                ->check(['file' => $file]);
            
            $savename = Filesystem::disk('public')->putFile('files', $file);
            if (!$savename) {
                 throw new \Exception("文件保存失败");
            }
            $url = '/storage/' . str_replace('\\', '/', $savename);

            // Record attachment
            $admin = Session::get('admin_user');
            Db::name('attachment')->insert([
                'url' => $url,
                'admin_id' => $admin['id'] ?? 0,
                'status' => 0,
                'type' => 'file',
                'create_time' => time()
            ]);

            return json([
                "errno" => 0,
                "data" => [
                    "url" => $url,
                    "name" => $file->getOriginalName()
                ]
            ]);
        } catch (ValidateException $e) {
            return json(["errno" => 1, "message" => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Upload File Error: ' . $e->getMessage());
             return json(["errno" => 1, "message" => "服务器错误: " . $e->getMessage()]);
        }
    }

    /**
     * 上传视频
     */
    public function video()
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        try {
            $file = request()->file('file');
            if (!$file) {
                $file = request()->file('wangeditor-uploaded-video');
            }

            if (!$file) {
                 return json(["errno" => 1, "message" => "未接收到视频文件"]);
            }

            validate(['file' => 'fileExt:mp4,webm,ogg|fileSize:104857600']) // 100MB
                ->check(['file' => $file]);
            
            $savename = Filesystem::disk('public')->putFile('videos', $file);
            if (!$savename) {
                 throw new \Exception("视频保存失败");
            }
            $url = '/storage/' . str_replace('\\', '/', $savename);

            // Record attachment
            $admin = Session::get('admin_user');
            Db::name('attachment')->insert([
                'url' => $url,
                'admin_id' => $admin['id'] ?? 0,
                'status' => 0,
                'type' => 'video',
                'create_time' => time()
            ]);

            return json([
                "errno" => 0,
                "data" => [
                    "url" => $url
                ]
            ]);
        } catch (ValidateException $e) {
            return json(["errno" => 1, "message" => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Upload Video Error: ' . $e->getMessage());
             return json(["errno" => 1, "message" => "服务器错误: " . $e->getMessage()]);
        }
    }
}
