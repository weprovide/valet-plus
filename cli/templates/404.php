<html>
    <head>
        <title>Valet - Not Found - <?php echo htmlspecialchars($siteName . '.' . $valetConfig['domain']); ?></title>

        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                text-align: center;
                font-size: 25px;
                font-weight: 200;
            }

            h1 {
                font-size: 50px;
                font-weight: 200;
            }

            h2 {
                font-weight: 200;
            }

            a {
                color: #fff;
                text-decoration: none;
            }

            span {
                background: #000;
                color: #fff;
                padding-left: 10px;
                padding-right: 10px;
                padding-bottom: 5px;
                padding-top: 5px;
            }
        </style>
    </head>
    <body>
        <div>
            <h1>Valet could not find directory <span><?php echo htmlspecialchars($siteName); ?></span></h1>
            <?php
            // Only show for localhost (acessing from 127.0.0.1, or any lan ip)
            if ($_SERVER['REMOTE_ADDR'] == $_SERVER['SERVER_ADDR']) {
            ?>
                <h2>Available sites:</h2>
                <?php foreach ($valetConfig['paths'] as $path): ?>
                    <?php foreach (glob(htmlspecialchars($path) . '/*', GLOB_ONLYDIR) as $site): ?>
                        <p>
                            <span><a href="http://<?php echo htmlspecialchars(basename($site) . '.' . $valetConfig['domain']); ?>"><?php echo htmlspecialchars(basename($site) . '.' . $valetConfig['domain']); ?></a></span>
                        </p>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <h2>Checked paths:</h2>
                <?php foreach ($valetConfig['paths'] as $path): ?>
                    <p><span><?php echo htmlspecialchars($path); ?></span></p>
                <?php endforeach; ?>
            <?php
            }
            ?>
        </div>
    </body>
</html>
