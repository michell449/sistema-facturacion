    <div class="login-box">
      <div class="card card-outline card-primary">
        <div class="card-header">
              <h1 class="mb-0 text-center">Reestablecer contraseña</h1>
        </div>
        <div class="card-body login-card-body">
          <p class="login-box-msg">Ingresa tu cuenta para recuperar contraseña.</p>
          <form action="core/restorepassword.php" method="post">
            <div class="input-group mb-1">
              <div class="form-floating">
                <input id="email" type="email"  name="email" required autofocus class="form-control" value="" placeholder="" />
                <label for="email">Email</label>
              </div>
              <div class="input-group-text"><span class="bi bi-envelope"></span></div>
            </div>
            <!--begin::Row-->
            <div class="row">
                <div class="d-grid gap-2">
                  <button type="submit" class="btn btn-primary"> Recuperar </button>
                </div>
            </div>
            <!--end::Row-->
          </form>
          <p class="mb-1"><a href="login">Iniciar sesión</a></p>
        </div>
        <!-- /.login-card-body -->
      </div>
    </div>
    <!-- /.login-box -->