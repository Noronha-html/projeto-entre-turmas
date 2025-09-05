<?php
include_once 'connection.php';

function instantiateProducts($catID) {
  GLOBAL $conn;

  $sql = 'SELECT p.CategoriaID, p.ProdutoID, p.Nome, p.Descricao, p.Preco, p.Imagem FROM produtos as p
            INNER JOIN categorias as c ON p.CategoriaID = c.CategoriaID';
  $result = mysqli_query($conn, $sql);

  while($row = mysqli_fetch_assoc($result)) {
    if($row['CategoriaID'] == $catID) {
      echo '<a href="product.php?id='.$row['ProdutoID'].'" class="produto"><img src="'.$row['Imagem'].'"><h3>'.$row['Nome'].'</h3><p>'.$row['Preco'].'</p></a>';
    }
  }
}
?>

<!DOCTYPE html>
    <html lang="pt-BR">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <title>Minha Loja</title>
    </head>
    <body>
    
    <header>
      <h1>Minha Loja</h1>
      <p>Encontre o que você procura em nossas categorias</p>
    </header>
    
    <nav>
      <a href="#pet">Pet</a>
      <a href="#eletronicos">Eletrônicos</a>
      <a href="#beleza">Beleza</a>
      <a href="#comida">Comida</a>
      <a href="#esportes">Esportes</a>
    </nav>
    
    <!-- Categoria 1 -->
    <section id="pet">
      <h2>Pet</h2>
      <div class="grid">
        <?php
          instantiateProducts(9);
        ?>
      </div>
    </section>
    
    <!-- Categoria 2 -->
    <section id="eletronicos">
      <h2>Eletrônicos</h2>
      <div class="grid">
        <?php
          instantiateProducts(5);
        ?>
      </div>
    </section>
    
    <!-- Categoria 3 -->
    <section id="beleza">
      <h2>Beleza</h2>
      <div class="grid">
        <?php
          instantiateProducts(6);
        ?>
      </div>
    </section>
    
    <!-- Categoria 4 -->
    <section id="comida">
      <h2>Chocolates</h2>
      <div class="grid">
        <?php
          instantiateProducts(7);
        ?>
      </div>
    </section>
    
    <!-- Categoria 5 -->
    <section id="esportes">
      <h2>Esportes</h2>
      <div class="grid">
        <?php
          instantiateProducts(8);
        ?>
      </div>
    </section>
    
    </body>
    </html>
