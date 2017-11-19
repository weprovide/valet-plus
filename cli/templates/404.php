<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Valet - Not Found - <?php echo htmlspecialchars($siteName . '.' . $valetConfig['domain']); ?></title>

    <link rel="stylesheet" href="https://opensource.keycdn.com/fontawesome/4.7.0/font-awesome.min.css"
          integrity="sha384-dNpIIXE8U05kAbPhy3G1cz+yZmTzA6CY8Vg/u2L9xRnHjJiAK76m2BIEaSEV+/aU" crossorigin="anonymous">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css"
          integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    <style>
        body {
            overflow-x: hidden;
        }

        .fehler {
            background: #fed024;
        }

        .show {
            background: #2a2025;
            color: #fff;
        }

        .show h3 {
            margin-bottom: 35px;
        }

        .show a {
            color: #fed024;
            text-decoration: none;
        }

        .fehler h1 {
            color: #313131;
        }

        h1 {
            margin: 0 !important;
            font-size: 6em;
        }

        .content {
            text-align: center;
            width: 100%;
        }

        .icon {
            font-size: 4em;
            transition: all .2s;
        }

        .icon:hover {
            transform: scale(1.1);
            color: #f00;
        }

        .noCenter {
            text-align: left;
        }

        .text {
            font-size: 1.3em;
            font-weight: 300;
        }

        .message {
            margin-top: 35px;
        }

        .down {
            text-align: center;
            position: absolute;
            font-size: 2.5em;
            width: 100%;
            cursor: pointer;
            bottom: 20px;
        }

        .animated {
            animation-duration: 2s;
            animation-fill-mode: both;
            animation-timing-function: ease-in-out;
            animation-iteration-count: infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-30px);
            }
            60% {
                transform: translateY(-15px);
            }
        }

        .bounce {
            animation-name: bounce;
        }

        .table thead {
            border-bottom: 3px solid #fff;
        }

        .table tbody tr {
            border-bottom: 1px solid #fff;
        }

        .fullHeight {
            height: 100vh;
            display: flex !important;
            align-items: center;
            justify-content: center;
        }

        .table-body {
            max-height: 200px;
            overflow-x: hidden;
            overflow-y: scroll
        }

        ::-webkit-scrollbar-track, ::-webkit-scrollbar, ::-webkit-scrollbar-thumb {
            border-radius: 5px;
        }

        ::-webkit-scrollbar-track {
            -webkit-box-shadow: inset 0 0 6px rgba(0, 0, 0, 0.3);
            background-color: #F5F5F5;
        }

        ::-webkit-scrollbar {
            width: 10px;
            background-color: #F5F5F5;
        }

        ::-webkit-scrollbar-thumb {
            background-color: #fed024;
            border: 2px solid #2a2025;
        }
    </style>
</head>
<body>
<main>
    <div class="fehler container-fluid fullHeight">
        <div class="content" id="contentOne">
            <h1>404</h1>
            <div class="row">
                <div class="col-md-3"></div>
                <div class="col-md-6 message">
                    <p class="row">
                    </p>
                    <div class="col-xs-4">
                        <i class="fa fa-exclamation-triangle icon" aria-hidden="true"></i>
                    </div>
                    <div class="col-xs-8 noCenter text">
                        Error! The page does not exist. See if you've written your URL correctly or look below for
                        available sites.
                    </div>
                    <p></p>
                </div>
                <div class="col-md-3"></div>
            </div>
        </div>

        <div class="down">
            <i class="fa fa-angle-down bounce animated" aria-hidden="true"></i>
        </div>
    </div>
    <div class="show container-fluid fullHeight">
        <div class="content" id="contentTwo">
            <div class="row">
                <div class="col-md-4 col-md-offset-2 noCenter">
                    <h3>Available sites:</h3>
                    <div class="table-body">
                        <table class="table text">
                            <thead>
                            <tr>
                                <td>Site</td>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($valetConfig['paths'] as $path) : ?>
                                <?php foreach (glob(htmlspecialchars($path) . '/*', GLOB_ONLYDIR) as $site) : ?>
                                    <tr>
                                        <td>
                                            <a href="http://<?php echo htmlspecialchars(basename($site) . '.' . $valetConfig['domain']); ?>"
                                               target="_blank">
                                                <?php echo htmlspecialchars(basename($site) . '.' . $valetConfig['domain']); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-4 noCenter">
                    <h3>Checked paths:</h3>
                    <div class="table-body">
                        <table class="table text">
                            <thead>
                            <tr>
                                <td>Path</td>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($valetConfig['paths'] as $path) : ?>
                                <tr>
                                    <td>
                                        <a href="#">
                                            <?php echo htmlspecialchars($path); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script type="application/javascript">
    document.getElementsByClassName('down')[0].onclick = function () {
        window.scroll({top: document.getElementsByClassName('show')[0].offsetTop, behavior: 'smooth'});
    };
</script>

</body>
</html>