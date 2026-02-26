<?php
declare(strict_types=1);

namespace app\index\controller;

use app\BaseController;
use think\facade\Filesystem;
use think\exception\ValidateException;
use think\facade\Log;
use think\facade\Session;
use think\facade\Db;

/**
 * 前台文件上传控制器
 */
class Upload extends BaseController
{
    /**
     * 控制器初始化 - 检查前台用户登录
     */
    protected function initialize()
    {
        if (!Session::has('user')) {
             echo json_encode(["errno" => 1, "message" => "请先登录后再上传文件"]);
             exit;
        }
    }

    /**
     * 上传图片（用于 wangEditor）
     */
    public function image()
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        try {
            $file = request()->file('file');
            if (!$file) {
                $file = request()->file('wangeditor-uploaded-image');
            }

            if (!$file) {
                return json(["errno" => 1, "message" => "未接收到文件"]);
            }

            // 验证扩展名和大小 (2MB) - 前台上传限制稍微严格点
            validate(['file' => 'fileExt:jpg,png,gif,jpeg,webp|fileSize:2097152']) 
                ->check(['file' => $file]);
            
            $savename = Filesystem::disk('public')->putFile('images', $file);
            
            if (!$savename) {
                 throw new \Exception("文件保存失败");
            }
            
            $url = '/storage/' . str_replace('\\', '/', $savename);

            // Record attachment
            $user = Session::get('user');
            Db::name('attachment')->insert([
                'url' => $url,
                'user_id' => $user['id'] ?? 0,
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
            Log::error('Front Upload Error: ' . $e->getMessage());
            return json(["errno" => 1, "message" => "服务器错误"]);
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

            // 验证扩展名和大小 (100MB)
            validate(['file' => 'fileExt:mp4,webm,ogg|fileSize:104857600']) 
                ->check(['file' => $file]);
            
            $savename = Filesystem::disk('public')->putFile('videos', $file);
            if (!$savename) {
                 throw new \Exception("视频保存失败");
            }
            $url = '/storage/' . str_replace('\\', '/', $savename);

            // Record attachment
            $user = Session::get('user');
            Db::name('attachment')->insert([
                'url' => $url,
                'user_id' => $user['id'] ?? 0,
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
            Log::error('Front Upload Video Validate Error: ' . $e->getMessage());
            return json(["errno" => 1, "message" => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Front Upload Video Error: ' . $e->getMessage());
            return json(["errno" => 1, "message" => "服务器错误"]);
        }
    }
}
