<?php
// $error e $success podem ser preenchidos pelo controller em respostas síncronas.
// No fluxo modal com AJAX, tratamos as mensagens via JSON; ainda assim, deixamos legados.

?>
<form id="modalForm" method="post" action="<?= base_url('alterar-senha') ?>">
  <?php csrf_field(); ?>
  <div class="form-group">
    <label>Nova Senha</label>
    <input name="nova_senha" type="password" class="form-control" required minlength="8" autocomplete="new-password">
    <small class="form-text text-muted">Mínimo de 8 caracteres.</small>
  </div>
  <div class="form-group">
    <label>Confirmar Senha</label>
    <input name="confirma_senha" type="password" class="form-control" required minlength="8" autocomplete="new-password">
  </div>
  <div id="modalFormFeedback" class="mt-2"></div>
</form>

<script>
(function(){
  var $form = $('#modalForm');
  $form.on('submit', function(e){
    e.preventDefault();

    $('#modalFormFeedback').html('');
    var data = $form.serialize();

    $.ajax({
      url: $form.attr('action') + '?ajax=1',
      method: 'POST',
      data: data,
      dataType: 'json'
    }).done(function(resp){
      if (resp.ok) {
        $('#modalFormFeedback').html('<div class="alert alert-success py-1 my-2">'+ (resp.message || 'Senha alterada com sucesso.') +'</div>');
        // Fecha o modal após 1.2s
        setTimeout(function(){
          $('#genericModal').modal('hide');
        }, 1200);
      } else {
        $('#modalFormFeedback').html('<div class="alert alert-danger py-1 my-2">'+ (resp.message || 'Falha ao alterar senha.') +'</div>');
      }
    }).fail(function(xhr){
      let msg = 'Erro ao salvar ('+xhr.status+').';
      if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
      $('#modalFormFeedback').html('<div class="alert alert-danger py-1 my-2">'+ msg +'</div>');
    });
  });
})();
</script>