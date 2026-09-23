<?php

?>
<html>
<head>
    <title>KSO | Login</title>

    <link rel="stylesheet" href="<?= base_url('assets/login/bootstrap-4.1.3.min.css') ?>">
    <script src="<?= base_url('assets/login/login.js') ?>" defer></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <link rel="icon" href="/api/kso.svg">

    <style>
        /* Added */
        .has-float-label {
            display: block;
            position: relative;
        }

        a.btn.btn-lg{
            color: #fff!important;
        }
        .has-float-label label,
        .has-float-label > span {
            color: grey;
            position: absolute;
            left: 0;
            top: 0;
            cursor: text;
            font-size: 120%;
            opacity: 1;
            -webkit-transition: all .3s;
            transition: all .3s;
        }

        .has-float-label select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        .has-float-label textarea {
            width: 100%;
        }

        .has-float-label input,
        .has-float-label select,
        .has-float-label textarea {
            font-size: 15px;
            font-weight: normal !important;
            padding-top: 1.3em;
            margin-bottom: 2px;
            border: 0;
            height: 45px;
            border-radius: 0;
            border-bottom: 2px solid rgba(0, 0, 0, .1);
        }

        .has-float-label input::-webkit-input-placeholder,
        .has-float-label select::-webkit-input-placeholder,
        .has-float-label textarea::-webkit-input-placeholder {
            opacity: 1;
            -webkit-transition: all .2s;
            transition: all .2s;
        }

        .has-float-label input::-moz-placeholder,
        .has-float-label select::-moz-placeholder,
        .has-float-label textarea::-moz-placeholder {
            opacity: 1;
            transition: all .2s;
        }

        .has-float-label input:-ms-input-placeholder,
        .has-float-label select:-ms-input-placeholder,
        .has-float-label textarea:-ms-input-placeholder {
            opacity: 1;
            transition: all .2s;
        }

        .has-float-label input::placeholder,
        .has-float-label select::placeholder,
        .has-float-label textarea::placeholder {
            opacity: 1;
            -webkit-transition: all .2s;
            transition: all .2s;
        }

        .has-float-label input:invalid:not(:focus)::-webkit-input-placeholder,
        .has-float-label select:invalid:not(:focus)::-webkit-input-placeholder,
        .has-float-label textarea:invalid:not(:focus)::-webkit-input-placeholder {
            opacity: 0;
        }

        .has-float-label input:invalid:not(:focus)::-moz-placeholder,
        .has-float-label select:invalid:not(:focus)::-moz-placeholder,
        .has-float-label textarea:invalid:not(:focus)::-moz-placeholder {
            opacity: 0;
        }

        .has-float-label input:invalid:not(:focus):-ms-input-placeholder,
        .has-float-label select:invalid:not(:focus):-ms-input-placeholder,
        .has-float-label textarea:invalid:not(:focus):-ms-input-placeholder {
            opacity: 0;
        }

        .has-float-label input:invalid:not(:focus)::placeholder,
        .has-float-label select:invalid:not(:focus)::placeholder,
        .has-float-label textarea:invalid:not(:focus)::placeholder {
            opacity: 0;
        }

        .has-float-label input:invalid:not(:focus) + *,
        .has-float-label select:invalid:not(:focus) + *,
        .has-float-label textarea:invalid:not(:focus) + * {
            font-size: 140%;
            opacity: .5;
            top: 1.3em;
        }

        .has-float-label input:focus,
        .has-float-label select:focus,
        .has-float-label textarea:focus {
            outline: 0;
            border-color: #2563eb;
        }


        /*  Added End */

        .form-control {
            height: auto;
        }

        :root {
            --input-padding-x: 1.5rem;
            --input-padding-y: .75rem;
        }

        body {
            background: #26313c;
            /* fallback for old browsers */
            background: -webkit-linear-gradient(to top, #26313c, #1c252e);
            /* Chrome 10-25, Safari 5.1-6 */
            background: linear-gradient(to top, #26313c, #1c252e);
            /* W3C, IE 10+/ Edge, Firefox 16+, Chrome 26+, Opera 12+, Safari 7+ */
        }

        .card-signin {
            border: 0;
            border-radius: .5rem;
            box-shadow: 0 0.5rem 1rem 0 rgba(0, 0, 0, 0.1);
        }

        .card-signin .card-title {
            margin-bottom: 2rem;
            font-weight: 300;
            font-size: 1.5rem;
        }

        .card-signin .card-body {
            padding: 2rem;
        }

        .form-signin {
            width: 100%;
        }

        .btn {
            font-size: 80%;
            border-radius:, 5rem;
            letter-spacing: .1rem;
            font-weight: bold;
            padding: 1rem;
            transition: all 0.2s;
        }

        .btn.btn-primary {
            background: #26313c;
            border-color: #26313c;
        }

        .btn.btn-warning {
            background: #464646;
            border-color: #464646;
        }

        a,
        a:link,
        .text-primary {
            color: #26313c !important;
        }

        .form-label-group {
            position: relative;
            margin-bottom: 1rem;
        }

        .form-label-group input {
            border-radius: .5rem;
        }

        .form-label-group > input,
        .form-label-group > label {
            padding: var(--input-padding-y) var(--input-padding-x);
        }

        .form-label-group > label {
            position: absolute;
            top: 0;
            left: 0;
            display: block;
            width: 100%;
            margin-bottom: 0;
            /* Override default `<label>` margin */
            line-height: 1.5;
            color: #495057;
            border: 1px solid transparent;
            border-radius: .25rem;
            transition: all .1s ease-in-out;
        }

        .form-label-group input::-webkit-input-placeholder {
            color: transparent;
        }

        .form-label-group input:-ms-input-placeholder {
            color: transparent;
        }

        .form-label-group input::-ms-input-placeholder {
            color: transparent;
        }

        .form-label-group input::-moz-placeholder {
            color: transparent;
        }

        .form-label-group input::placeholder {
            color: transparent;
        }

        .form-label-group input:not(:placeholder-shown) {
            padding-top: calc(var(--input-padding-y) + var(--input-padding-y) * (2 / 3));
            padding-bottom: calc(var(--input-padding-y) / 3);
        }

        .form-label-group input:not(:placeholder-shown) ~ label {
            padding-top: calc(var(--input-padding-y) / 3);
            padding-bottom: calc(var(--input-padding-y) / 3);
            font-size: 12px;
            color: #777;
        }

        .copyright {
            transform: translate(0px, 20px);
            transition: all .2s ease-out;
            opacity: .75;
            font-size: 70%;
            cursor: default;
        }

        .copyright:hover {
            opacity: 1;
        }

        .logo {
            position: fixed;
            bottom: 24px;
            right: 24px;
        }

        .btn-secondary a, .btn-secondary a:hover {
            color: white;
        }

        .hidden {
            display: none !important;
        }

        .icon {
            vertical-align: -0.125em;
        }
            /* KSO's mark and name at the top of the card. */
        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: 600;
            letter-spacing: .04em;
            color: #26313c;
        }

</style>
</head>

<body>

<div class="container">
    <div class="row">
        <div class="col-sm-9 col-md-7 col-lg-5 mx-auto">
            <div class="card card-signin my-5">
                <div class="card-body">
                    <div class="brand">
                        <img src="/api/kso.svg" width="40" height="40" alt="">
                        <span>KSO</span>
                    </div>

                    <h5 class="card-title text-center"><svg class="icon text-primary" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg> Password renewal</h5>
                    <div class="alert alert-warning clearfix <?= isset(DebugTool\Data::getStore()['description']) ? '' : '' ?>" role="alert" id="validation">
                        <p><?= esc(DebugTool\Data::getStore()['description'] ?? '') ?></p>

                        <div  id="pswd_info_b"  class="pswd_info">
                            <p>Password requirement</p>
                            <ul>
                                <li id="letter" class="invalid">At least <strong>one letter</strong></li>
                                <li id="capital" class="invalid">At least <strong>one uppercase letter</strong></li>
                                <li id="number" class="invalid">At least <strong>one number</strong></li>
                                <li id="length" class="invalid">At least <strong>eight characters</strong></li>
                                <li id="same" class="invalid">Must be identical</li>
                            </ul>
                        </div>
                    </div>

                    <form class="form-signin" method="post">
                        <?= csrf_field() ?>
                        <div class="form-label-group">
                            <input type="password" id="password" name="password" class="form-control" placeholder="Password" required autofocus autocomplete="new-password">
                            <label for="password">Password</label>
                        </div>

                        <div class="form-label-group">
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Repeat password" required>
                            <label for="password_confirm">Repeat password</label>
                        </div>

                        <?php if(isset(DebugTool\Data::getStore()['description'])) { ?>
                        <div class="alert alert-warning" role="alert"><?= esc(DebugTool\Data::getStore()['description']) ?></div>
                        <?php } ?>

                        <button class="btn btn-lg btn-primary btn-block text-uppercase" type="submit">Save</button>
                        
                        <div class="text-muted small text-center copyright">Copyright © 2018-<?= date('Y') ?> ·
                            <a href="https://4spaces.dk" target="_blank"> 4 Spaces ApS</a>
                        </div>

                    </form>


                </div>
            </div>
            <!--
            <a href="https://www.4spaces.dk" target="_blank" class="logo"><img alt="4 Spaces" src="<?= base_url('assets/login/four-spaces-white.svg') ?>" width="16"></a>
            -->
        </div>
    </div>
</div>


</body>
</html>
