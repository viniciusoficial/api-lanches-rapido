#!/bin/sh
set -eu

admin_name=${BOOTSTRAP_ADMIN_NAME:-}
admin_phone=${BOOTSTRAP_ADMIN_PHONE:-}
admin_password=${BOOTSTRAP_ADMIN_PASSWORD:-}

if [ -n "$admin_name" ] || [ -n "$admin_phone" ] || [ -n "$admin_password" ]; then
    if [ -z "$admin_name" ] || [ -z "$admin_phone" ] || [ -z "$admin_password" ]; then
        echo "Defina BOOTSTRAP_ADMIN_NAME, BOOTSTRAP_ADMIN_PHONE e BOOTSTRAP_ADMIN_PASSWORD juntos nas variáveis do Render." >&2
        exit 1
    fi

    php -r '
        require "/app/src/Store.php";
        $name = trim((string)getenv("BOOTSTRAP_ADMIN_NAME"));
        $phone = preg_replace("/\\D+/", "", (string)getenv("BOOTSTRAP_ADMIN_PHONE"));
        $password = (string)getenv("BOOTSTRAP_ADMIN_PASSWORD");
        if (strlen($name) < 2 || strlen($phone) < 10 || strlen($password) < 12) {
            fwrite(STDERR, "Dados inválidos: use nome, telefone com DDD e senha com pelo menos 12 caracteres.\n");
            exit(1);
        }
        $store = new Store("/app/data");
        $users = $store->all("users");
        foreach ($users as $index => $user) {
            if (($user["phone"] ?? "") !== $phone) continue;
            if (($user["role"] ?? "") !== "admin") {
                fwrite(STDERR, "Esse telefone já pertence a uma conta que não é admin. Escolha outro número.\n");
                exit(1);
            }
            $users[$index]["name"] = $name;
            $users[$index]["password"] = password_hash($password, PASSWORD_DEFAULT);
            $store->save("users", $users);
            echo "Conta admin inicial atualizada.\n";
            exit(0);
        }
        $users[] = [
            "id" => bin2hex(random_bytes(12)),
            "name" => $name,
            "phone" => $phone,
            "password" => password_hash($password, PASSWORD_DEFAULT),
            "role" => "admin"
        ];
        $store->save("users", $users);
        echo "Conta admin inicial criada.\n";
    '
fi

exec php -S "0.0.0.0:${PORT:-10000}" -t /app/public /app/public/index.php
