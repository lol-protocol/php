        <li class="celda">
          <a href="/marcalibro/<?= $this->e($item['id']) ?>">
            <img class="tira" src="/catalogo/<?= $this->e($item['id']) ?>.svg" width="1400" height="200" alt="<?= $this->e($item['alt']) ?>" loading="lazy" decoding="async">
            <span class="celda__nombre" aria-hidden="true"><?= $this->e($item['motif']) ?></span>
          </a>
        </li>
