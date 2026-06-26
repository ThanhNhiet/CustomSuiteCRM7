<?php
namespace Api\V8\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SugarBean;

/**
 * FileController
 * Custom API endpoint phục vụ lưu trữ file và tích hợp sinh trắc học Face++ cho App Timekeeping Service
 * API endpoint: GET/POST /file/{moduleName}/{id}
 */
class FileController
{
    /**
     * Get file based on module and record ID
     */
    public function getFile(Request $request, Response $response, array $args)
    {
        $moduleName = $args['module'];
        $id = $args['id'];
        $downloadParam = $request->getQueryParams()['download'] ?? false;
        $previewParam = $request->getQueryParams()['preview'] ?? false;
        
        if (empty($moduleName) || empty($id)) {
            return $this->errorResponse($response, 'Missing required parameters', 400);
        }
        
        $moduleNameLower = strtolower($moduleName);
        $uploadDir = '/var/www/html/demo/nhansu/upload';
        $exactFilePath = $this->findFile($uploadDir, $id);
        
        if (empty($exactFilePath)) {
            $exactFilePath = $this->findFile($uploadDir, $id . '_photo');
        }
        
        if (empty($exactFilePath)) {
            return $this->errorResponse($response, "File not found for ID: {$id}", 404);
        }
        
        $mimeType = $this->getMimeType($exactFilePath, $moduleNameLower, $id);
        $filename = $this->getFilename($exactFilePath, $moduleNameLower, $id);
        $isImage = $this->isImageFile($mimeType);
        $baseUrl = $this->getBaseUrl();
        
        if ($downloadParam === 'true' || $previewParam === 'true') {
            $fileContents = file_get_contents($exactFilePath);
            if ($fileContents === false) {
                return $this->errorResponse($response, "Unable to read file contents", 500);
            }
            
            $response = $response->withHeader('Content-Type', $mimeType);
            if ($isImage && $previewParam === 'true') {
                $response = $response
                    ->withHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
                    ->withHeader('Content-Length', filesize($exactFilePath))
                    ->withHeader('Cache-Control', 'public, max-age=86400');
            } else if ($downloadParam === 'true') {
                $response = $response
                    ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                    ->withHeader('Content-Length', filesize($exactFilePath));
            } else {
                $response = $response
                    ->withHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
                    ->withHeader('Content-Length', filesize($exactFilePath));
            }
            
            $response->getBody()->write($fileContents);
            return $response;
        }
        
        $fileName = basename($exactFilePath);
        $nativeUrl = $baseUrl . "/custom/public/file_preview.php?id={$fileName}&type={$moduleName}&preview=true";
        $downloadUrl = $baseUrl . "/custom/public/file_preview.php?id={$fileName}&type={$moduleName}&preview=false";
        
        $originalId = $id;
        if (empty(strpos($fileName, '_photo')) && strpos($fileName, $id) === 0) {
            $originalId = $id;
        } elseif (strpos($fileName, '_photo') !== false) {
            $originalId = $id . '_photo';
        }
        $downloadUrlNative = $baseUrl . "/index.php?entryPoint=download&id={$originalId}&type={$moduleName}";
        
        $result = [
            'success' => true,
            'mime_type' => $mimeType,
            'filename' => $filename,
            'is_image' => $isImage,
            'native_url' => $nativeUrl,
            'download_url' => $downloadUrl,
            'download_url_native' => $downloadUrlNative
        ];
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    private function findFile($directory, $searchPattern)
    {
        if (!is_dir($directory)) {
            return null;
        }
        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (strpos($file, $searchPattern) === 0) {
                return $directory . '/' . $file;
            }
        }
        return null;
    }
    
    private function getMimeType($filePath, $moduleName, $id)
    {
        global $beanList;
        if (isset($beanList[$moduleName])) {
            $beanClass = $beanList[$moduleName];
            $bean = new $beanClass();
            if ($bean->retrieve($id)) {
                if (!empty($bean->file_mime_type)) {
                    return $bean->file_mime_type;
                }
            }
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        return empty($mimeType) ? 'application/octet-stream' : $mimeType;
    }
    
    private function getFilename($filePath, $moduleName, $id)
    {
        global $beanList;
        if (isset($beanList[$moduleName])) {
            $beanClass = $beanList[$moduleName];
            $bean = new $beanClass();
            if ($bean->retrieve($id)) {
                if (!empty($bean->filename)) {
                    return $bean->filename;
                }
            }
        }
        return basename($filePath);
    }
    
    private function isImageFile($mimeType)
    {
        return strpos($mimeType, 'image/') === 0;
    }
    
    private function getBaseUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = '';
        
        if (strpos($scriptPath, 'Api') !== false) {
            $baseDir = substr($scriptPath, 0, strpos($scriptPath, 'Api'));
        } else {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($requestUri, 'Api') !== false) {
                $baseDir = substr($requestUri, 0, strpos($requestUri, 'Api'));
            }
        }
        
        $baseDir = rtrim($baseDir, '/');
        if (!empty($baseDir)) {
            return $protocol . $domainName . $baseDir;
        }
        
        $configFile = '/var/www/html/demo/nhansu/config.php';
        if (file_exists($configFile)) {
            include_once $configFile;
            if (isset($sugar_config) && isset($sugar_config['site_url'])) {
                return rtrim($sugar_config['site_url'], '/');
            }
        }
        return $protocol . $domainName;
    }
    
    public function uploadFile(Request $request, Response $response, array $args)
    {
        $moduleName = $args['module'];
        $id = $args['id'];
        
        if (empty($moduleName) || empty($id)) {
            return $this->errorResponse($response, 'Missing required parameters', 400);
        }
        
        $uploadedFiles = $request->getUploadedFiles();
        if (!isset($uploadedFiles['file'])) {
            return $this->errorResponse($response, 'No file uploaded', 400);
        }
        
        $file = $uploadedFiles['file'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->errorResponse($response, 'File upload failed with error code: ' . $file->getError(), 400);
        }
        
        $originalFilename = $file->getClientFilename();
        $mimeType = $file->getClientMediaType();
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $uploadDir = '/var/www/html/demo/nhansu/upload';
        
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            return $this->errorResponse($response, 'Upload directory is not writable', 500);
        }
        
        $targetFilename = '';
        if ($moduleName === 'AOS_Products') {
            $timestamp = date('dmYHis');
            $targetFilename = $timestamp . '_' . $originalFilename;
            if ($extension) {
                $targetFilename = substr($targetFilename, 0, strrpos($targetFilename, '.')) . '.' . $extension;
            }
        } elseif ($moduleName === 'Users') {
            $targetFilename = $id . '_photo';
        } else {
            $targetFilename = $id;
        }
        
        $targetPath = $uploadDir . '/' . $targetFilename;
        
        try {
            $file->moveTo($targetPath);
            $this->updateFileInfoInDb($moduleName, $id, $originalFilename, $mimeType);
            
            $baseUrl = $this->getBaseUrl();
            $fileUrl = rtrim($baseUrl, '/') . '/upload/' . $targetFilename;
            $previewUrl = $baseUrl . "/custom/public/file_preview.php?id={$targetFilename}&type={$moduleName}&preview=true";
            $downloadUrl = $baseUrl . "/custom/public/file_preview.php?id={$targetFilename}&type={$moduleName}&preview=false";
            
            $result = [
                'success' => true,
                'message' => 'File uploaded successfully',
                'filename' => $targetFilename,
                'original_filename' => $originalFilename,
                'mime_type' => $mimeType,
                'file_url' => $fileUrl,
                'preview_url' => $previewUrl,
                'download_url' => $downloadUrl
            ];
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to save uploaded file: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Helper tối ưu & nén ảnh nếu vượt quá dung lượng quy định
     */
    private function compressImageIfNeeded(string $filePath, string $targetPath, float $maxSizeMb = 1.5): string
    {
        $logger = $GLOBALS['log'];
        if (!file_exists($filePath)) {
            $logger->warn("Face++ [Compress Helper]: File không tồn tại để kiểm tra kích thước: {$filePath}");
            return $filePath;
        }

        $fileSize = filesize($filePath);
        if ($fileSize <= $maxSizeMb * 1024 * 1024) {
            $logger->info("Face++ [Compress Helper]: Dung lượng ảnh (" . round($fileSize / 1024) . " KB) dưới mức giới hạn {$maxSizeMb}MB. Không cần nén.");
            return $filePath;
        }

        $logger->info("Face++ [Compress Helper]: Tiến hành nén ảnh gốc nặng: " . round($fileSize / 1024) . " KB");
        $imageInfo = @getimagesize($filePath);
        $mime = $imageInfo['mime'] ?? 'image/jpeg';
        $srcImg = null;

        if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
            $srcImg = @imagecreatefromjpeg($filePath);
        } elseif ($mime == 'image/png') {
            $srcImg = @imagecreatefrompng($filePath);
        }

        if ($srcImg) {
            $oldW = imagesx($srcImg); 
            $oldH = imagesy($srcImg);
            $newW = ($oldW > 1200) ? 1200 : $oldW;
            $newH = floor($oldH * ($newW / $oldW));

            $canvas = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($canvas, $srcImg, 0, 0, 0, 0, $newW, $newH, $oldW, $oldH);
            
            if (imagejpeg($canvas, $targetPath, 75)) {
                imagedestroy($srcImg); 
                imagedestroy($canvas);
                $logger->info("Face++ [Compress Helper]: Nén thành công! File tạm lưu tại: {$targetPath}, kích thước mới: " . round(filesize($targetPath) / 1024) . " KB");
                return $targetPath;
            }
            imagedestroy($srcImg); 
            imagedestroy($canvas);
        }
        return $filePath;
    }

    /**
     * Upload file API endpoint without record ID + Tích hợp nhận diện Face++ (Rút gọn LOG)
     */
    public function sendFileWithoutId(Request $request, Response $response, array $args)
    {
        $moduleName = $args['module'] ?? null;
        if (empty($moduleName)) {
            $GLOBALS['log']->error("Face++ LỖI: Thiếu tham số bắt buộc 'module'");
            return $this->errorResponse($response, 'Missing required parameter: module', 400);
        }
        
        $uploadedFiles = $request->getUploadedFiles();
        if (!isset($uploadedFiles['file'])) {
            $GLOBALS['log']->error("Face++ LỖI: Request gửi lên không chứa biến file nhị phân.");
            return $this->errorResponse($response, 'No file uploaded', 400);
        }
        
        $file = $uploadedFiles['file'];
        if ($file->getSize() === 0 || $file->getError() !== UPLOAD_ERR_OK) {
            $GLOBALS['log']->error("Face++ LỖI: File rỗng hoặc quá trình upload file tạm bị lỗi. Mã: " . $file->getError());
            return $this->errorResponse($response, 'File upload failed or empty data.', 400);
        }

        $tempFilePath = $file->getStream()->getMetadata('uri');
        $baseUrl = $this->getBaseUrl();
        $uploadDir = '/var/www/html/demo/nhansu/upload';

        $isMatch = false;
        $dbAvatarName = "";
        $finalAvatarPath = "";
        $finalTempFilePath = "";
        $avatarPath = "";

        try {
            if ($moduleName === 'sgt_attendance') {
                $queryParams = $request->getQueryParams();
                $parsedBody = $request->getParsedBody(); 
                $empNo = $queryParams['emp_no'] ?? $parsedBody['emp_no'] ?? $_GET['emp_no'] ?? null;

                if (empty($empNo)) {
                    $GLOBALS['log']->error("Face++ LỖI: Thiếu tham số 'emp_no'.");
                    return $this->errorResponse($response, "Missing required parameter: emp_no", 400);
                }

                global $db;
                $safeEmpNo = $db->quote($empNo); 
                
                $sql = "SELECT emp.id, cstm.profile_image_c 
                        FROM sgt_employees emp
                        LEFT JOIN sgt_employees_cstm cstm ON emp.id = cstm.id_c
                        WHERE emp.ma_nv = '{$safeEmpNo}' AND emp.deleted = 0";
                        
                $resultDb = $db->query($sql, true);
                $row = $db->fetchByAssoc($resultDb);
                
                $dbAvatarName = $row['profile_image_c'] ?? "";
                $empId = $row['id'] ?? "";

                if (empty($dbAvatarName) && empty($empId)) {
                    $GLOBALS['log']->error("Face++ LỖI: Không tìm thấy nhân viên khớp với mã: {$empNo}");
                    return $this->errorResponse($response, "Nhân viên chưa có cấu hình ảnh chân dung gốc!", 400);
                }

                $suiteCrmFileName = $empId . '_profile_image_c';
                $avatarPath = $uploadDir . '/' . $suiteCrmFileName;

                if (!file_exists($avatarPath)) {
                    $avatarPath = $uploadDir . '/' . $dbAvatarName;
                }

                if (!file_exists($avatarPath)) {
                    $GLOBALS['log']->error("Face++ LỖI: File ảnh gốc không tồn tại vật lý trên server.");
                    return $this->errorResponse($response, "Không tìm thấy file ảnh gốc trên máy chủ.", 400);
                }

                $compressedAvatarPath = $uploadDir . '/temp_comp_av_' . $empNo . '.jpg';
                $compressedCapturePath = $uploadDir . '/temp_comp_cap_' . $empNo . '.jpg';

                $finalAvatarPath = $this->compressImageIfNeeded($avatarPath, $compressedAvatarPath);
                $finalTempFilePath = $this->compressImageIfNeeded($tempFilePath, $compressedCapturePath);

                // Gửi dữ liệu sang Face++
                $apiKey = '-NZ0_FfvEI-CVOcmKqyriqFKneoDxU9J';
                $apiSecret = 'oLm--eChg6r5dMv07it67djEEgr0zweV';
                $faceppUrl = 'https://api-us.faceplusplus.com/facepp/v3/compare';

                $postData = [
                    'api_key'      => $apiKey,
                    'api_secret'   => $apiSecret,
                    'image_file1'  => new \CURLFile($finalAvatarPath, 'image/jpeg', 'avatar.jpg'),
                    'image_file2'  => new \CURLFile($finalTempFilePath, 'image/jpeg', 'captured.jpg')
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $faceppUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                $curlResponse = curl_exec($ch);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($curlError) {
                    $GLOBALS['log']->fatal("Face++ API LỖI KẾT NỐI: CURL thất bại. Lỗi: {$curlError}");
                    return $this->errorResponse($response, 'Lỗi kết nối máy chủ AI: ' . $curlError, 500);
                }

                $aiResult = json_decode($curlResponse, true);
                if (!isset($aiResult['confidence'])) {
                    $errorMsg = $aiResult['error_message'] ?? 'Không nhận diện được khuôn mặt.';
                    $GLOBALS['log']->error("Face++ API LỖI NHẬN DIỆN: {$errorMsg}");
                    return $this->errorResponse($response, 'Lỗi nhận diện: ' . $errorMsg, 400);
                }

                $confidence = $aiResult['confidence'];
                
                if ($confidence >= 75.0) {
                    $isMatch = true;
                } else {
                    $GLOBALS['log']->warn("Face++ KẾT QUẢ: Xác thực thất bại cho nhân viên {$empNo} (Độ khớp: {$confidence}%)");
                    return $this->errorResponse($response, "Xác thực khuôn mặt thất bại! Độ khớp chân dung chỉ đạt {$confidence}%.", 400);
                }
            }

            $suiteCrmPhysicalName = (!empty($empId)) ? $empId . '_profile_image_c' : $dbAvatarName;
            $physicalFileNameToRender = file_exists($uploadDir . '/' . $suiteCrmPhysicalName) ? $suiteCrmPhysicalName : $dbAvatarName;

            $fileUrl     = rtrim($baseUrl, '/') . '/upload/' . $physicalFileNameToRender;
            $previewUrl  = $baseUrl . "/custom/public/file_preview.php?id={$physicalFileNameToRender}&type={$moduleName}&preview=true";
            $downloadUrl = $baseUrl . "/custom/public/file_preview.php?id={$physicalFileNameToRender}&type={$moduleName}&preview=false";

            $result = [
                'success'           => true,
                'is_match'          => $isMatch,
                'profile_image_c'   => $dbAvatarName, 
                'preview_url'       => $previewUrl,
                'file_url'          => $fileUrl,
                'download_url'      => $downloadUrl,
                'message'           => 'Xác thực sinh trắc học thành công. Hợp lệ để điểm danh.'
            ];
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $GLOBALS['log']->fatal("Face++ EXCEPTION CAUGHT: " . $e->getMessage());
            return $this->errorResponse($response, 'Failed to process face verification: ' . $e->getMessage(), 500);
        } finally {
            // Dọn dẹp file nén tạm bợ để tránh tràn dung lượng ổ đĩa
            if (!empty($finalAvatarPath) && $finalAvatarPath !== $avatarPath && file_exists($finalAvatarPath)) {
                @unlink($finalAvatarPath);
            }
            if (!empty($finalTempFilePath) && $finalTempFilePath !== $tempFilePath && file_exists($finalTempFilePath)) {
                @unlink($finalTempFilePath);
            }
        }
    }
    
    private function generateUniqueFilename($moduleName, $originalFilename, $extension)
    {
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $timestamp = date('dmYHis');
        $targetFilename = $timestamp . '_' . $uuid;
        if ($extension) {
            $targetFilename .= '.' . $extension;
        }
        return $targetFilename;
    }
    
    private function updateFileInfoInDb($moduleName, $id, $filename, $mimeType)
    {
        global $beanList;
        if (!isset($beanList[$moduleName])) {
            return;
        }
        $beanClass = $beanList[$moduleName];
        $bean = new $beanClass();
        if ($bean->retrieve($id)) {
            if (property_exists($bean, 'filename')) {
                $bean->filename = $filename;
            }
            if (property_exists($bean, 'file_mime_type')) {
                $bean->file_mime_type = $mimeType;
            }
            if (property_exists($bean, 'file_ext')) {
                $bean->file_ext = pathinfo($filename, PATHINFO_EXTENSION);
            }
            $bean->save();
        }
    }

    private function errorResponse(Response $response, $message, $statusCode = 400)
    {
        $result = [
            'success' => false,
            'message' => $message,
        ];
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
}