<form method="post" action="/admin/instances">
  <?= csrf_field() ?>
  <div class="grid cols-2">
    <div class="card">
      <div class="card-head"><h2>基本信息</h2></div>
      <div class="card-body">
        <div class="form-row">
          <label>实例名称</label>
          <input type="text" name="name" placeholder="留空自动生成">
        </div>
        <div class="form-row">
          <label>归属用户</label>
          <select name="user_id">
            <option value="0">管理员所有</option>
            <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['email']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label>节点</label>
          <select name="node_id" id="nodeSel">
            <?php foreach ($nodes as $n): ?>
              <option value="<?= (int)$n['id'] ?>"><?= e($n['name']) ?> · <?= e($n['ip']) ?>:<?= (int)$n['port'] ?> · <?= (int)$n['status'] === 1 ? '在线' : '离线' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid cols-2">
          <div class="form-row">
            <label>虚拟化类型</label>
            <select name="variant"><option value="container">容器 (LXC)</option><option value="vm">虚拟机 (KVM)</option></select>
          </div>
          <div class="form-row">
            <label>存储池（留空用节点默认）</label>
            <input type="text" name="storage_pool" placeholder="default">
          </div>
        </div>
        <div class="form-row">
          <label>系统镜像（来自所选节点）</label>
          <select name="source" id="imageSel" required></select>
          <div class="form-hint" id="imageHint">正在加载镜像…</div>
        </div>
        <div class="grid cols-2">
          <div class="form-row"><label>Root 密码</label><input type="text" name="root_password" placeholder="留空自动生成"></div>
          <div class="form-row"><label>登录用户</label><input type="text" name="os_user" placeholder="root / Administrator"></div>
        </div>
        <div class="form-row"><label>备注</label><input type="text" name="remark"></div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h2>资源限制</h2></div>
      <div class="card-body">
        <div class="grid cols-2">
          <div class="form-row"><label>CPU 核心</label><input type="number" name="cpu" value="1" min="1"></div>
          <div class="form-row"><label>内存（MB）</label><input type="number" name="memory_mb" value="512" min="128"></div>
          <div class="form-row"><label>磁盘（GB）</label><input type="number" name="disk_gb" value="15" min="1"></div>
          <div class="form-row"><label>入站带宽（Mbit，0=不限）</label><input type="number" name="inbound_mbps" value="0" min="0"></div>
          <div class="form-row"><label>出站带宽（Mbit，0=不限）</label><input type="number" name="outbound_mbps" value="0" min="0"></div>
          <div class="form-row"><label>磁盘读（MB/s，0=不限）</label><input type="number" name="disk_read_mbs" value="0" min="0"></div>
          <div class="form-row"><label>磁盘写（MB/s，0=不限）</label><input type="number" name="disk_write_mbs" value="0" min="0"></div>
          <div class="form-row"><label>CPU 上限（%）</label><input type="number" name="cpu_allowance" value="100" min="0" max="100"></div>
          <div class="form-row"><label>最大进程数（0=不限）</label><input type="number" name="processes" value="0" min="0"></div>
        </div>
        <div class="grid cols-3">
          <div class="form-row"><label>嵌套</label><select name="nesting"><option value="0">false</option><option value="1">true</option></select></div>
          <div class="form-row"><label>特权</label><select name="privileged"><option value="0">false</option><option value="1">true</option></select></div>
          <div class="form-row"><label>Swap</label><select name="swap"><option value="1">true</option><option value="0">false</option></select></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>网络与高级</h2></div>
    <div class="card-body">
      <div class="grid cols-2">
        <div class="form-row"><label>IP 分配方式</label><select name="ip_mode"><option value="static">静态</option><option value="dhcp">DHCP</option></select></div>
        <div class="form-row"><label>指定 IP（可选）</label><input type="text" name="ip" placeholder="留空由节点分配"></div>
        <div class="form-row"><label>主机名（可选）</label><input type="text" name="hostname"></div>
      </div>
      <div class="form-row"><label>cloud-init user-data（可选）</label><textarea name="user_data" placeholder="#cloud-config&#10;packages:&#10;  - nginx"></textarea></div>
      <div class="form-row"><label>额外 Incus 配置（每行 key=value）</label><textarea name="config_raw" placeholder="security.nesting=true"></textarea></div>
    </div>
  </div>

  <div class="form-actions">
    <button class="btn" type="submit">开通</button>
    <a class="btn ghost" href="/admin/instances">返回</a>
  </div>
</form>

<script>
(function () {
  var nodeSel = document.getElementById('nodeSel');
  var imageSel = document.getElementById('imageSel');
  var hint = document.getElementById('imageHint');

  function loadImages() {
    var nodeId = nodeSel.value || '';
    imageSel.innerHTML = '';
    hint.textContent = '正在加载镜像…';
    fetch('/admin/instances/images?node_id=' + encodeURIComponent(nodeId), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        var imgs = (res.data && res.data.images) || [];
        if (!imgs.length) {
          imageSel.innerHTML = '<option value="">（该节点暂无镜像，请先在节点上导入）</option>';
        } else {
          imgs.forEach(function (im) {
            var o = document.createElement('option');
            o.value = im.name;
            o.textContent = im.name + (im.type ? '  [' + im.type + ']' : '');
            imageSel.appendChild(o);
          });
        }
        hint.textContent = (res.data && res.data.warning) ? ('提示：' + res.data.warning) : '镜像来自该节点的 Incus（本地别名或远程源）。';
      })
      .catch(function () { hint.textContent = '加载镜像失败，请检查节点是否在线。'; });
  }
  nodeSel.addEventListener('change', loadImages);
  if (nodeSel.value) { loadImages(); }
})();
</script>
