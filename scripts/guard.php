<?php

/*
|--------------------------------------------------------------------------
| Guard for the destructive maintenance scripts
|--------------------------------------------------------------------------
|
| clean_database.php and reset_auto_increment.php rewrite whatever database
| .env happens to point at. Neither asked anything before doing it, which was
| survivable while they lived only on one machine and is not once the repo is
| public: the natural thing to do after cloning something is to run what is in
| it.
|
| Two conditions, both required:
|
|   1. APP_ENV must be "local". A script that empties tables has no business
|      running anywhere else, and the environment is the one check the operator
|      cannot forget to think about.
|   2. The operator must say so - either --force on the command line, or "yes"
|      typed at the prompt. The prompt names the database it is about to touch,
|      because "local" is a setting and not a promise about which server the
|      credentials point at.
|
| Include after the Laravel bootstrap, since both checks read config.
|
*/

if (! function_exists('guard_destructive_script')) {
    /**
     * @param string $script What is running, for the messages.
     * @param string $effect One line describing what is about to be destroyed.
     */
    function guard_destructive_script(string $script, string $effect): void
    {
        $environment = app()->environment();

        if ($environment !== 'local') {
            fwrite(STDERR, <<<MESSAGE

            {$script} refused to run.

            APP_ENV is "{$environment}". This script is destructive and only runs
            when APP_ENV=local.

            MESSAGE);

            exit(1);
        }

        $connection = config('database.default');
        $database   = config("database.connections.{$connection}.database");
        $host       = config("database.connections.{$connection}.host");
        $port       = config("database.connections.{$connection}.port");

        $forced = in_array('--force', $_SERVER['argv'] ?? [], true);

        fwrite(STDOUT, <<<MESSAGE

        {$script}
        {$effect}

        Database: {$database} on {$host}:{$port}

        MESSAGE);

        if ($forced) {
            fwrite(STDOUT, "Proceeding: --force was given.\n\n");

            return;
        }

        fwrite(STDOUT, "Type 'yes' to proceed, anything else to abort: ");

        $answer = trim((string) fgets(STDIN));

        if ($answer !== 'yes') {
            fwrite(STDOUT, "\nAborted. Nothing was changed.\n");

            exit(1);
        }

        fwrite(STDOUT, "\n");
    }
}
