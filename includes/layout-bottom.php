<?php
declare(strict_types=1);
/** @var bool $bare */
?>
<?php if ($bare): ?>
  </div>
<?php else: ?>
      </div>
    </div>
  </div>
<?php endif; ?>
<dialog id="dlg-confirma">
  <header class="dialog-h"><h2 id="confirma-titulo">Confirmar</h2><button class="dlg-x" type="button" data-close-dialog aria-label="Fechar">×</button></header>
  <div class="dialog-b"><p id="confirma-texto">Confirma esta ação?</p></div>
  <footer class="dlg-foot">
    <button class="btn" type="button" data-close-dialog>Não</button>
    <button class="btn btn-danger" type="button" id="confirma-sim">Sim</button>
  </footer>
</dialog>
<script src="assets/js/app.js?v=11"></script>
</body>
</html>
