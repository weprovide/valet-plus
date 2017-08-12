<html>
    <head>
        <title>Squire - Not Found - <?php echo htmlspecialchars($siteName . '.' . $squireConfig['domain']); ?></title>

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
            <h1>Squire could not find directory <span><?php echo htmlspecialchars($siteName); ?></span></h1>
            <h2>Available sites:</h2>
            <?php foreach($squireConfig['paths'] as $path): ?>
                <?php foreach(glob(htmlspecialchars($path) . '/*', GLOB_ONLYDIR) as $site): ?>
                    <p><span><a href="http://<?php echo htmlspecialchars(basename($site) . '.' . $squireConfig['domain']); ?>"><?php echo htmlspecialchars(basename($site) . '.' . $squireConfig['domain']); ?></a></span></p>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <h2>Checked paths:</h2>
            <?php foreach($squireConfig['paths'] as $path): ?>
                <p><span><?php echo htmlspecialchars($path); ?></span></p>
            <?php endforeach; ?>
        </div>
    </body>
</html>
