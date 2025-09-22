<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header">
            <h1 class="mb-0 text-center"><b>Admin</b>RJE</h1>
        </div>
        <div class="card-body login-card-body">
            <?php if ($_SESSION['ERROR_MSG'] <> "") { ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $_SESSION['ERROR_MSG']; ?>
                </div> <?php } ?>
            <p class="login-box-msg">Inicie sesiÃ³n para comenzar.</p>
            <form action="core/login.php" method="post">
                <div class="input-group mb-1">
                    <div class="form-floating">
                        <input id="loginEmail" type="email" name="email" class="form-control" value="" placeholder="" required />
                        <label for="loginEmail">Email</label>
                    </div>
                    <div class="input-group-text"><span class="bi bi-envelope"></span></div>
                </div>
                <div class="input-group mb-1">
                    <div class="form-floating">
                        <input id="loginPassword" type="password" name="password" class="form-control" placeholder="" required />
                        <label for="loginPassword">Password</label>
                    </div>
                    <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
                </div>
                <!--begin::Row-->
                <div class="row">
                    <div class="col-8 d-inline-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault" />
                            <label class="form-check-label" for="flexCheckDefault">Mantener sesiÃ³n</label>
                        </div>
                    </div>
                    <!-- /.col -->
                    <div class="col-2">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary"> Iniciar </button>
                        </div>
                    </div>
                    <!-- /.col -->
                </div>
                <!--end::Row-->
            </form>
            <p class="mb-1"><a href="forgotpassword">Olvide mi contraseÃ±a</a></p>
        </div>
        <!-- /.login-card-body -->
    </div>
</div>
<!-- /.login-box -->