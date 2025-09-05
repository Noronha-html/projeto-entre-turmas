<?php

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'lojateste';

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    http_response_code(500);
    echo "Erro na conexão com o banco: " . htmlspecialchars(mysqli_connect_error());
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    echo "Produto não informado ou id inválido.";
    exit;
}

$sql = "SELECT ProdutoID, Nome, preco, Descricao, Imagem FROM produtos WHERE ProdutoID = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    http_response_code(500);
    echo "Erro na preparação da consulta: " . htmlspecialchars(mysqli_error($conn));
    exit;
}
mysqli_stmt_bind_param($stmt, 'i', $id);

if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    echo "Erro ao executar a consulta: " . htmlspecialchars(mysqli_stmt_error($stmt));
    exit;
}

$product = null;
if (function_exists('mysqli_stmt_get_result')) {
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        $product = mysqli_fetch_assoc($res);
        mysqli_free_result($res);
    }
} else {
    mysqli_stmt_bind_result($stmt, $pid, $pnome, $ppreco, $pdescricao, $pimagem);
    if (mysqli_stmt_fetch($stmt)) {
        $product = [
            'ProdutoID' => $pid,
            'Nome' => $pnome,
            'preco' => $ppreco,
            'Descricao' => $pdescricao,
            'Imagem' => $pimagem
        ];
    }
}
mysqli_stmt_close($stmt);

if (!$product) {
    http_response_code(404);
    echo "<!doctype html><meta charset='utf-8'><title>Produto não encontrado</title>";
    echo "<div style='font-family:system-ui,Arial; padding:30px;'><h1>Produto não encontrado</h1>";
    echo "<p>O produto solicitado não existe.</p>";
    echo "<p><a href='index.php'>&larr; Voltar para a lista</a></p></div>";
    exit;
}

function format_real($valor) {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

$imageUrlRaw = isset($product['Imagem']) ? trim((string)$product['Imagem']) : '';
$useImage = null;

if ($imageUrlRaw !== '') {
    $lower = strtolower($imageUrlRaw);
    if (strpos($lower, 'http://') === 0 || strpos($lower, 'https://') === 0 || strpos($imageUrlRaw, '//') === 0) {
        $useImage = $imageUrlRaw;
    } else {
        $try = 'https://' . ltrim($imageUrlRaw, '/');
        if (filter_var($try, FILTER_VALIDATE_URL)) {
            $useImage = $try;
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($product['Nome']) ?> — Produto</title>
  <link rel="stylesheet" href="css/product.css">
  <style>
    .product-media img { display:block; max-width:100%; max-height:100%; object-fit:contain; }
    .product-media .placeholder { display: none; }
    .product-media.no-image img { display: none; }
    .product-media.no-image .placeholder { display:flex; align-items:center; justify-content:center; }
  </style>
</head>
<body>
  <main class="container">
    <a class="back" href="index.php">&larr; Voltar</a>

    <article class="product-card">
      <div class="product-media <?= $useImage ? '' : 'no-image' ?>">
        <?php if ($useImage): ?>
          <img src="<?= htmlspecialchars($useImage) ?>"
               alt="<?= htmlspecialchars($product['Nome']) ?>"
               loading="lazy"
               onerror="(function(img){ img.style.display='none'; img.parentNode.classList.add('no-image'); })(this);">
        <?php endif; ?>

        <div class="placeholder" aria-hidden="true" style="width:220px;height:220px;border-radius:12px;background:linear-gradient(135deg,#e6fffb,#cffafe);font-size:64px;color:#0b918f;font-weight:700;">
          <span><?= htmlspecialchars(mb_substr($product['Nome'], 0, 1)) ?></span>
        </div>
      </div>

      <div class="product-info">
        <h1 class="product-title"><?= htmlspecialchars($product['Nome']) ?></h1>
        <div class="product-price"><?= $product['preco'] ?></div>
        <div class="product-description">
          <?= nl2br(htmlspecialchars($product['Descricao'])) ?>
        </div>

        <div class="product-actions">
          <a class="btn-primary" href="cart.php?add=<?= (int)$product['ProdutoID'] ?>">Adicionar ao carrinho</a>
          <a class="btn-secondary" href="index.php">Continuar navegando</a>
        </div>
      </div>
    </article>
  </main>
</body>
</html>
<?php
mysqli_close($conn);
