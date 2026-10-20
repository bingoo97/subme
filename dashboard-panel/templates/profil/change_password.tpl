<div class="content-box customer-password customer-secondary-page">
    <h2>{$t.change_password_title}</h2>
    <p>{$t.change_password_intro}</p>
    <hr />
    <form action="" method="post" class="form-horizontal" autocomplete="off">
        <input type="hidden" name="_csrf" value="{$csrf_token|default:''}">
        <div class="form-group">
            <label for="customer-current_password" class="col-sm-2 control-label">{$t.change_password_current}:</label>
            <div class="col-sm-10">
                <input type="password" class="form-control" id="customer-current_password" name="current_password" placeholder="{$t.change_password_current}..." required />
            </div>
        </div>
        <div class="form-group">
            <label for="customer-new_password" class="col-sm-2 control-label">{$t.change_password_new}:</label>
            <div class="col-sm-10">
                <input type="password" class="form-control" id="customer-new_password" name="new_password" placeholder="{$t.change_password_new}..." required />
            </div>
        </div>
        <div class="form-group">
            <label for="customer-new_password_repeat" class="col-sm-2 control-label">{$t.change_password_repeat}:</label>
            <div class="col-sm-10">
                <input type="password" class="form-control" id="customer-new_password_repeat" name="new_password_repeat" placeholder="{$t.change_password_repeat}..." required />
            </div>
        </div>
        <div class="form-group">
            <div class="col-sm-offset-2 col-sm-10">
                <button type="submit" name="change_password" class="btn btn-primary">
                    <i class="fa fa-check-circle"></i> {$t.change_password_submit}
                </button>
            </div>
        </div>
	</form>
</div>
