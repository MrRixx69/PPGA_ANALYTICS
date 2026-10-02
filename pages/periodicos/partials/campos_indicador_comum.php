<div class="indicador-evidencia">

    <div class="indicador-campos indicador-campos-3">

        <div class="form-group">
            <label class="titulo-campo">Status da evidência</label>
            <select name="indicadores[<?= e($baseFormulario) ?>][status]">
                <?php foreach (['PENDENTE', 'CONFIRMADO', 'DIVERGENTE'] as $statusOpcao): ?>
                    <option
                        value="<?= $statusOpcao ?>"
                        <?= ($indicadorComum['status'] ?? 'PENDENTE') === $statusOpcao ? 'selected' : '' ?>
                    >
                        <?= $statusOpcao ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="titulo-campo">Revisor</label>
            <input
                type="text"
                name="indicadores[<?= e($baseFormulario) ?>][revisado_por]"
                value="<?= e($indicadorComum['revisado_por'] ?? '') ?>"
                placeholder="Nome de quem conferiu"
            >
        </div>

        <div class="form-group">
            <label class="titulo-campo">URL da evidência</label>
            <input
                type="url"
                name="indicadores[<?= e($baseFormulario) ?>][url_evidencia]"
                value="<?= e($indicadorComum['url_evidencia'] ?? '') ?>"
                placeholder="https://..."
            >
        </div>

    </div>

    <div class="form-group">
        <label class="titulo-campo">Observação</label>
        <input
            type="text"
            name="indicadores[<?= e($baseFormulario) ?>][observacao]"
            value="<?= e($indicadorComum['observacao'] ?? '') ?>"
            placeholder="Categoria, detalhe da consulta ou justificativa"
        >
    </div>

</div>
