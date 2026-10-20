<div class="admin-customer-import__notice" data-import-notice role="status" hidden></div>
<section class="admin-customer-import__preview" data-import-preview hidden>
    <div class="admin-customer-import__heading"><strong>Podgląd importu</strong><span data-import-format></span></div>
    <div class="admin-customer-import__stats" data-import-stats></div>
    <div class="admin-customer-import__help">Istniejące konta pomijamy — nie zmieniamy ich haseł, statusów ani salda. Handle powstanie z e-maila; w razie kolizji dodamy końcówkę. IP i kraj z pliku pomijamy.</div>
    <details class="admin-customer-import__mapping"><summary>Rozpoznane kolumny i interpretacja statusu</summary>
        <div class="row g-2">
            <?php foreach(['email'=>'E-mail','password'=>'Hasło','status'=>'Status'] as $field=>$label): ?>
                <label class="col-sm-4"><?php echo $label; ?><select class="form-select" name="<?php echo $field; ?>_column" data-import-map="<?php echo $field; ?>"><option value="auto">Automatycznie</option></select></label>
            <?php endforeach; ?>
            <label class="col-sm-8">Status liczbowy<select class="form-select" name="numeric_status"><option value="auto">Automatycznie: 1 = aktywny; dla blocked/disabled odwrotnie</option><option value="one_active">1 = aktywny, 0 = nieaktywny</option><option value="zero_active">0 = aktywny, 1 = nieaktywny</option></select></label>
        </div>
        <p>Nieznany status ustawi konto jako nieaktywne. Po zmianie kolumn kliknij ponownie „Sprawdź dane”.</p>
    </details>
    <div class="table-responsive"><table class="table admin-table"><thead><tr><th>E-mail / handle</th><th>Status</th><th>Hasło</th></tr></thead><tbody data-import-sample></tbody></table></div>
    <p class="admin-customer-import__help" data-import-password-help></p>
</section>
<div class="admin-customer-import__progress" data-import-progress hidden>
    <strong data-import-progress-title></strong><progress data-import-progress-bar max="100" value="0"></progress><span data-import-progress-text></span>
</div>
<div class="admin-customer-import__actions">
    <button type="button" class="btn btn-success" data-import-start hidden><i class="bi bi-people" aria-hidden="true"></i> Rozpocznij import</button>
    <button type="button" class="btn btn-outline-dark" data-import-pause hidden>Wstrzymaj</button>
    <button type="button" class="btn btn-outline-dark" data-import-resume hidden>Wznów import</button>
    <a class="btn btn-light" href="/admin/?page=users" data-import-refresh hidden>Zobacz klientów</a>
</div>
