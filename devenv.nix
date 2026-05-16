{ pkgs, ... }:

{
  env = {
    LANG = "C.UTF-8";
    LC_ALL = "C.UTF-8";
    LC_CTYPE = "C.UTF-8";
    LC_COLLATE = "C.UTF-8";
    TEST_DATABASE_URI = "postgres://user:secret@127.0.0.1:5432/ledgering";
  };

  languages.php = {
    enable = true;
    version = "8.3";

    extensions = [
      "decimal"
      "pdo_mysql"
      "pdo_pgsql"
      "pdo_sqlite"
      "xdebug"
    ];

    ini = ''
      memory_limit = 512M
      max_execution_time = 60
      xdebug.mode = debug,develop,coverage
    '';
  };

  services.postgres = {
    enable = true;
    listen_addresses = "127.0.0.1";
    initialDatabases = [{ name = "ledgering"; }];
    initialScript = ''
      CREATE USER "user" WITH PASSWORD 'secret';
      GRANT ALL PRIVILEGES ON DATABASE ledgering TO "user";
      ALTER DATABASE ledgering OWNER TO "user";
    '';
  };

  packages = [
    pkgs.git
    pkgs.unzip
  ];

  scripts.test.exec = ''
    composer test
  '';

  scripts.analyse.exec = ''
    composer mago:analyze
  '';

  scripts.serve-coverage.exec = ''
    if [ ! -f .dev/coverage/index.html ]; then
      composer test
    fi

    php -S 127.0.0.1:8080 -t .dev/coverage
  '';
}
