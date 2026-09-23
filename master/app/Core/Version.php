<?php
namespace App\Core;

/**
 * 主控端版本信息。
 *
 * 版本号规则：MAJOR.MINOR.PATCH
 *   MAJOR 不兼容变更 / MINOR 新增功能 / PATCH 修复
 *
 * 修改版本号后，升级脚本/关于页会自动感知；
 * 被控端兼容下限见 MIN_AGENT_VERSION（低于该版本的被控端会在后台标记为「需更新」）。
 */
class Version
{
    /** 面板端版本 */
    public const MASTER = '2.2.0';

    /** 节点端程序版本（仅供参考，实际以节点 lib/Version.php 为准） */
    public const MIN_AGENT = '2.0.0';
}
