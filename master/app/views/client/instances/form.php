<form method="post" action="/user/instances">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-head"><h2>新建云服务器</h2></div>
    <div class="card-body">
      <div class="grid cols-2">
        <div class="form-row"><label>名称（可选）</label><input type="text" name="name" placeholder="留空自动生成"></div>
        <div class="form-row"><label>初始密码（可选）</label><input type="text" name="root_password" placeholder="留空自动生成"></div>
        <div class="form-row">
          <label>节点</label>
          <select name="node_id" id="nodeSel">
            <?php foreach ($nodes as $n): ?>
              <option value="<?= (int)$n['id'] ?>"><?= e($n['name']) ?> · <?= e($n['region']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label>操作系统</label>
          <select name="source" id="imageSel" required></select>
        </div>
      </div>
      <div class="grid cols-3">
        <div class="form-row"><label>CPU（核）</label><input type="number" name="cpu" value="1" min="1"></div>
        <div class="form-row"><label>内存（MB）</label><input type="number" name="memory_mb" value="512" min="128"></div>
        <div class="form-row"><label>磁盘（GB）</label><input type="number" name="disk_gb" value="15" min="1"></div>
      </div>
      <div class="form-hint" id="imageHint">正在加载镜像…</div>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit">创建</button>
    <a class="btn ghost" href="/user/instances">返回</a>
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
    fetch('/user/instances/images?node_id=' + encodeURIComponent(nodeId), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        var imgs = (res.data && res.data.images) || [];
        if (!imgs.length) { imageSel.innerHTML = '<option value="">（该节点暂无镜像）</option>'; }
        else { imgs.forEach(function (im) { var o = document.createElement('option'); o.value = im.name; o.textContent = im.name; imageSel.appendChild(o); }); }
        hint.textContent = (res.data && res.data.warning) ? ('提示：' + res.data.warning) : '';
      })
      .catch(function () { hint.textContent = '加载镜像失败。'; });
  }
  nodeSel.addEventListener('change', loadImages);
  if (nodeSel.value) { loadImages(); }
})();
</script>
