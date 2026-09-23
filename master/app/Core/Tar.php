<?php
namespace App\Core;

/**
 * 纯 PHP 实现的 tar (ustar) 打包器。
 * 不依赖 ext-zip / ext-zlib，用于把被控端代码打包供远程安装下载。
 */
class Tar
{
    private string $buffer = '';

    public function addDirectory(string $name): void
    {
        $name = rtrim($name, '/') . '/';
        $this->buffer .= $this->header($name, 0, '5');
    }

    public function addFile(string $name, string $content): void
    {
        $this->buffer .= $this->header($name, strlen($content), '0');
        $this->buffer .= $content;
        $pad = (512 - (strlen($content) % 512)) % 512;
        if ($pad > 0) {
            $this->buffer .= str_repeat("\0", $pad);
        }
    }

    /**
     * 递归把目录加入包中。$exclude 为正则数组。
     */
    public function addDirectoryTree(string $baseDir, string $prefix = '', array $exclude = []): void
    {
        $baseDir = rtrim($baseDir, '/');
        $items = scandir($baseDir);
        if ($items === false) {
            return;
        }
        sort($items);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $baseDir . '/' . $item;
            $rel = ($prefix !== '' ? $prefix . '/' : '') . $item;

            $skip = false;
            foreach ($exclude as $re) {
                if (preg_match($re, $rel)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            if (is_dir($path)) {
                $this->addDirectory($rel);
                $this->addDirectoryTree($path, $rel, $exclude);
            } else {
                $this->addFile($rel, (string)file_get_contents($path));
            }
        }
    }

    /** 返回完整 tar 内容（含结尾两个空块）。 */
    public function finish(): string
    {
        return $this->buffer . str_repeat("\0", 1024);
    }

    private function header(string $name, int $size, string $type): string
    {
        $name = ltrim($name, '/');
        $header  = str_pad(substr($name, 0, 100), 100, "\0");
        $header .= $this->octal(0644, 7) . "\0";   // mode
        $header .= $this->octal(0, 7) . "\0";      // uid
        $header .= $this->octal(0, 7) . "\0";      // gid
        $header .= $this->octal($size, 11) . "\0"; // size
        $header .= $this->octal(time(), 11) . "\0";// mtime
        $header .= '        ';                      // chksum 占位（8 空格）
        $header .= $type;                           // typeflag
        $header .= str_pad('', 100, "\0");          // linkname
        $header .= 'ustar' . "\0";                  // magic
        $header .= '00';                            // version
        $header .= str_pad('root', 32, "\0");       // uname
        $header .= str_pad('root', 32, "\0");       // gname
        $header .= $this->octal(0, 7) . "\0";       // devmajor
        $header .= $this->octal(0, 7) . "\0";       // devminor
        $header .= str_pad('', 155, "\0");          // prefix
        $header = str_pad($header, 512, "\0");

        // 校验和
        $sum = 0;
        for ($i = 0; $i < 512; $i++) {
            $sum += ord($header[$i]);
        }
        $checksum = sprintf('%06o', $sum) . "\0 ";
        return substr_replace($header, $checksum, 148, 8);
    }

    private function octal(int $value, int $length): string
    {
        return str_pad(decoct($value), $length, '0', STR_PAD_LEFT);
    }
}
