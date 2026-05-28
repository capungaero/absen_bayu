<div class="row vh-100 d-flex justify-content-center">
    <div class="col-12 align-self-center">
        <div class="row">
            <div class="col-lg-5 mx-auto">
                <div class="card">
                    <div class="card-body p-0 auth-header-box">
                        <div class="text-center p-3">
                            <a href="index.html" class="logo logo-admin">
                                <img src="<?= base_url() ?>assets/images/logo-box.png" height="100" alt="logo" class="auth-logo">
                            </a> 
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <ul class="nav-border nav nav-pills" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active font-weight-semibold" data-toggle="tab" href="#LogIn_Tab" role="tab">Lupa Password</a>
                            </li>
                        </ul>
                         <!-- Tab panes -->
                        <div class="tab-content">
                            <div class="tab-pane active p-3" id="LogIn_Tab" role="tabpanel">     

                                <?= $this->session->flashdata('alert_message') ?>

                                <form class="form-horizontal auth-form" action="<?= site_url('do_forget') ?>" method="POST">
                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
    
                                    <div class="form-group mb-2">
                                        <label for="username">Email</label>
                                        <div class="input-group">                                                                                         
                                            <input type="email" class="form-control" name="email" id="email" required="" placeholder="Masukkan email">
                                        </div>                                    
                                    </div><!--end form-group--> 
        
                                    <div class="form-group mb-0 row">
                                        <div class="col-12">
                                            <button class="btn btn-primary btn-block waves-effect waves-light">Reset Password</button>
                                        </div><!--end col--> 
                                    </div> <!--end form-group-->                           
                                </form><!--end form-->
                                <div class="m-3 text-center text-muted">
                                    <p class="mb-0">Sudah punya akun ?  <a href="<?= site_url('authentication/login') ?>" class="btn btn-soft-primary ml-2"><b>Login Disini</b></a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!--end card-->
            </div><!--end col-->
        </div><!--end row-->
    </div><!--end col-->
</div><!--end row-->