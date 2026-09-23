<?php
namespace Agent;

/**
 * 被控端版本。与主控端 master/app/Core/Version.php 对应。
 * 修改后主控端「关于与更新」页会将其与各节点上报版本比对。
 */
class Version
{
    public const VERSION = '2.2.1';
}
