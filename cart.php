<?php

session_start();

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

function fetch_product_by_id($conn, int $id) {
    $sql = "SELECT ProdutoID, Nome, Preco, Descricao, Imagem FROM produtos WHERE ProdutoID = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); return null; }

    if (function_exists('mysqli_stmt_get_result')) {
        $res = mysqli_stmt_get_result($stmt);
        $prod = $res ? mysqli_fetch_assoc($res) : null;
        if ($res) mysqli_free_result($res);
        mysqli_stmt_close($stmt);
        return $prod;
    } else {
        mysqli_stmt_bind_result($stmt, $pid, $pnome, $ppreco, $pdesc, $pimg);
        $prod = null;
        if (mysqli_stmt_fetch($stmt)) {
            $prod = [
                'ProdutoID' => $pid,
                'Nome' => $pnome,
                'Preco' => $ppreco,
                'Descricao' => $pdesc,
                'Imagem' => $pimg
            ];
        }
        mysqli_stmt_close($stmt);
        return $prod;
    }
}

function normalize_price($raw) {
    if ($raw === null || $raw === '') return 0.0;
    $s = trim((string)$raw);
    $s = preg_replace('/[^\d\.,\-]/u', '', $s);
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (strpos($s, ',') !== false) {
        $s = str_replace(',', '.', $s);
    }
    return (float)$s;
}

function format_real($valor) {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$redirect = false;
if (isset($_GET['add'])) {
    $id = filter_var($_GET['add'], FILTER_VALIDATE_INT);
    if ($id !== false && $id !== null) {
        $prod = fetch_product_by_id($conn, $id);
        if ($prod) {
            if (!isset($_SESSION['cart'][$id])) $_SESSION['cart'][$id] = 0;
            $_SESSION['cart'][$id] += 1;
        }
    }
    header('Location: cart.php');
    exit;
}

if (isset($_GET['remove'])) {
    $id = filter_var($_GET['remove'], FILTER_VALIDATE_INT);
    if ($id !== false && $id !== null) {
        unset($_SESSION['cart'][$id]);
    }
    header('Location: cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $quantities = $_POST['qty'] ?? [];
    foreach ($quantities as $k => $v) {
        $id = (int)$k;
        $q  = (int)$v;
        if ($q <= 0) {
            unset($_SESSION['cart'][$id]);
        } else {
            $_SESSION['cart'][$id] = $q;
        }
    }
    header('Location: cart.php');
    exit;
}

$checkout_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $checkout_result = ['error' => 'Nome e email são obrigatórios e e-mail deve ser válido.'];
    } elseif (empty($_SESSION['cart'])) {
        $checkout_result = ['error' => 'Carrinho vazio.'];
    } else {
        $items = [];
        $subtotal = 0.0;
        foreach ($_SESSION['cart'] as $pid => $qty) {
            $prod = fetch_product_by_id($conn, (int)$pid);
            if (!$prod) continue;
            $price = normalize_price($prod['Preco']);
            $items[] = [
                'ProdutoID' => $prod['ProdutoID'],
                'Nome' => $prod['Nome'],
                'Preco' => $price,
                'Quantidade' => $qty,
                'Subtotal' => $price * $qty,
            ];
            $subtotal += $price * $qty;
        }
        $shipping = ($subtotal > 0) ? 10.00 : 0.00;
        $total = $subtotal + $shipping;
        $orderNumber = 'PED' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $_SESSION['cart'] = [];
        $checkout_result = [
            'success' => true,
            'orderNumber' => $orderNumber,
            'customer' => ['name' => $name, 'email' => $email],
            'items' => $items,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total,
        ];
    }
}

$cartItems = [];
$subtotal = 0.0;
foreach ($_SESSION['cart'] as $pid => $qty) {
    $prod = fetch_product_by_id($conn, (int)$pid);
    if (!$prod) continue;
    $price = normalize_price($prod['Preco']);
    $cartItems[] = [
        'ProdutoID' => $prod['ProdutoID'],
        'Nome' => $prod['Nome'],
        'Preco' => $price,
        'Quantidade' => $qty,
        'Subtotal' => $price * $qty,
        'Imagem' => $prod['Imagem'],
    ];
    $subtotal += $price * $qty;
}
$shipping = ($subtotal > 0) ? 10.00 : 0.00;
$total = $subtotal + $shipping;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Carrinho — Loja</title>
  <link rel="stylesheet" href="css/cart.css">
</head>
<body>
  <main class="cart-container">
    <header class="cart-header">
      <h1>Meu Carrinho</h1>
      <a class="link-back" href="index.php">&larr; Voltar à loja</a>
    </header>

    <?php if ($checkout_result && isset($checkout_result['error'])): ?>
      <div class="notice notice-error"><?= htmlspecialchars($checkout_result['error']) ?></div>
    <?php endif; ?>

    <?php if ($checkout_result && isset($checkout_result['success'])): ?>
      <section class="order-confirm">
        <h2>Pedido confirmado — <?= htmlspecialchars($checkout_result['orderNumber']) ?></h2>
        <p>Obrigado, <?= htmlspecialchars($checkout_result['customer']['name']) ?>! Um e-mail de confirmação foi enviado para <strong><?= htmlspecialchars($checkout_result['customer']['email']) ?></strong> (simulação).</p>

        <div class="order-summary">
          <h3>Resumo do pedido</h3>
          <ul>
            <?php foreach ($checkout_result['items'] as $it): ?>
              <li>
                <?= htmlspecialchars($it['Nome']) ?> — <?= $it['Quantidade'] ?> x <?= format_real($it['Preco']) ?> = <strong><?= format_real($it['Subtotal']) ?></strong>
              </li>
            <?php endforeach; ?>
          </ul>
          <p>Subtotal: <strong><?= format_real($checkout_result['subtotal']) ?></strong></p>
          <p>Frete (simulado): <strong><?= format_real($checkout_result['shipping']) ?></strong></p>
          <p>Total: <strong><?= format_real($checkout_result['total']) ?></strong></p>
        </div>
      </section>
    <?php else: ?>
      <section class="cart-main">
        <?php if (empty($cartItems)): ?>
          <div class="empty">Seu carrinho está vazio. Você pode adicionar um produto visitando sua página (ex.: <code>product.php?id=1</code>) ou usando <code>?add=ID</code> na URL.</div>
        <?php else: ?>
          <form method="post" action="cart.php" class="cart-form">
            <input type="hidden" name="update_cart" value="1">
            <table class="cart-table">
              <thead>
                <tr>
                  <th>Produto</th>
                  <th>Preço unit.</th>
                  <th>Quantidade</th>
                  <th>Subtotal</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cartItems as $item): ?>
                  <tr>
                    <td class="prod-cell">
                      <?php if (!empty($item['Imagem'])): ?>
                        <img class="thumb" src="<?= htmlspecialchars($item['Imagem']) ?>" alt="<?= htmlspecialchars($item['Nome']) ?>" onerror="this.style.display='none'">
                      <?php endif; ?>
                      <div class="prod-meta">
                        <div class="prod-name"><?= htmlspecialchars($item['Nome']) ?></div>
                        <div class="prod-small"><?= htmlspecialchars(mb_substr($item['Nome'],0,80)) ?></div>
                      </div>
                    </td>
                    <td><?= format_real($item['Preco']) ?></td>
                    <td>
                      <input type="number" name="qty[<?= (int)$item['ProdutoID'] ?>]" value="<?= (int)$item['Quantidade'] ?>" min="0" class="qty-input">
                    </td>
                    <td><?= format_real($item['Subtotal']) ?></td>
                    <td><a class="remove" href="cart.php?remove=<?= (int)$item['ProdutoID'] ?>">Remover</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <div class="cart-actions">
              <button type="submit" class="btn" aria-label="Atualizar quantidades">Atualizar carrinho</button>
              <a class="btn ghost" href="index.php">Continuar comprando</a>
            </div>
          </form>

          <aside class="summary">
            <h3>Resumo</h3>
            <p>Subtotal: <strong><?= format_real($subtotal) ?></strong></p>
            <p>Frete (simulado): <strong><?= format_real($shipping) ?></strong></p>
            <p class="total">Total: <strong><?= format_real($total) ?></strong></p>

            <form method="post" class="checkout-form">
              <h4>Finalizar compra (simulação)</h4>
              <label>Nome completo<br>
                <input type="text" name="customer_name" required>
              </label>
              <label>E-mail<br>
                <input type="email" name="customer_email" required>
              </label>
              <button type="submit" name="checkout" class="btn primary">Finalizar compra</button>
            </form>
          </aside>
        <?php endif; ?>
      </section>
    <?php endif; ?>

  </main>
</body>
</html>
<?php
mysqli_close($conn);
