<?php
/*
Plugin Name: WooCommerce - Integrador de compras Greenn
Description: Plugin para integrar as compras feitas na Greenn com o WooCommerce da Academia do Importador.
Author: Filipe Barcellos
Version: 1.0
*/

/**
 * Registra mensagens de erro em um arquivo de log se o registro estiver habilitado nas opções do plugin.
 */
function greenn_log_error($message, $log_raw_data = false) {
    if (get_option('greenn_logging_enabled', 'no') === 'yes') {
        if ($log_raw_data && get_option('greenn_log_raw_data', 'no') !== 'yes') {
            return; // Não registra se a opção de dados brutos não estiver habilitada.
        }
        $log_file_path = get_option('greenn_log_file_path', plugin_dir_path(__FILE__) . 'greenn.log');
        if (!$log_file_path) {
            $log_file_path = plugin_dir_path(__FILE__) . 'greenn.log';
        }
        $date = date("Y-m-d H:i:s");
        $log_entry = sprintf("[%s] %s\n", $date, is_array($message) || is_object($message) ? print_r($message, true) : $message);
        error_log($log_entry, 3, $log_file_path);
    }
}

/**
 * Adiciona uma página de menu ao painel de administração do WordPress para o plugin.
 */
function greenn_add_admin_menu() {
    add_menu_page('Webhook Greenn', 'Webhook Greenn', 'manage_options', 'greenn_webhook', 'greenn_options_page');
}

/**
 * Exibe a página de opções do plugin no painel de administração.
 */
function greenn_options_page() {
    // Verifica se o usuário deseja limpar o log e tem a permissão para isso
    if (isset($_POST['greenn_clear_log']) && check_admin_referer('greenn_clear_log_action', 'greenn_clear_log_nonce')) {
        $log_file_path = get_option('greenn_log_file_path', plugin_dir_path(__FILE__) . 'greenn.log');
        file_put_contents($log_file_path, '');
        echo "<div class='updated'><p>Log limpo.</p></div>";
    }
    ?>
    <div class="wrap">
        <h2>Configurações do Webhook Greenn</h2>
        <form action="options.php" method="post">
            <?php
            settings_fields('greenn_logger_options');
            do_settings_sections('greenn_logger');
            submit_button();
            ?>
        </form>
        <form action="" method="post">
            <?php
            wp_nonce_field('greenn_clear_log_action', 'greenn_clear_log_nonce');
            submit_button('Limpar Log', 'delete', 'greenn_clear_log', false);
            ?>
        </form>
    </div>
    <?php
}

/**
 * Registra as configurações do plugin, como a opção de habilitar o registro de log
 * e o caminho do arquivo de log. Define as seções e campos na página de configurações.
 */
function greenn_register_settings() {
    register_setting('greenn_logger_options', 'greenn_logging_enabled');
    register_setting('greenn_logger_options', 'greenn_log_file_path');
    add_settings_section('greenn_logger_main', 'Configurações principais', 'greenn_logger_section_text', 'greenn_logger');
    add_settings_field('greenn_logging_enabled', 'Habilitar registro', 'greenn_logging_enabled_field', 'greenn_logger', 'greenn_logger_main');
    add_settings_field('greenn_log_file_path', 'Caminho do arquivo de log', 'greenn_log_file_path_field', 'greenn_logger', 'greenn_logger_main');
    add_settings_field('greenn_log_contents', 'Conteúdo do Log', 'greenn_log_contents_field', 'greenn_logger', 'greenn_logger_main');
  register_setting('greenn_logger_options', 'greenn_log_raw_data');
  add_settings_field('greenn_log_raw_data', 'Registrar Dados Brutos', 'greenn_log_raw_data_field', 'greenn_logger', 'greenn_logger_main');

}

/**
 * Exibe texto introdutório para a seção de configurações principais.
 */
function greenn_logger_section_text() {
    echo '<p>Configuração principal do Webhook Greenn.</p>';
}

/**
 * Campo para definir o caminho do arquivo de log na página de configurações.
 */
function greenn_log_file_path_field() {
    $log_file_path = get_option('greenn_log_file_path', plugin_dir_path(__FILE__) . 'greenn.log');
    echo "<input id='greenn_log_file_path' name='greenn_log_file_path' type='text' value='" . esc_attr($log_file_path) . "' />";
    echo "<input id='greenn_log_file_path' name='greenn_log_file_path' type='text' value='" . esc_attr($log_file_path) . "' />";

}
function greenn_log_raw_data_field() {
    $log_raw_data = get_option('greenn_log_raw_data', 'no');
    echo "<input id='greenn_log_raw_data' name='greenn_log_raw_data' type='checkbox' " . checked('yes', $log_raw_data, false) . " value='yes'> Registrar dados brutos recebidos no log";
}
/**
 * Campo para exibir o conteúdo do arquivo de log na página de configurações.
 */
function greenn_log_contents_field() {
    $log_file_path = get_option('greenn_log_file_path', plugin_dir_path(__FILE__) . 'greenn.log');
    if (file_exists($log_file_path)) {
        echo "<textarea readonly rows='10' cols='70'>" . esc_textarea(file_get_contents($log_file_path)) . "</textarea>";
    } else {
        echo "<p>Arquivo de log não encontrado. Verifique o caminho ou as permissões.</p>";
        echo "<textarea readonly rows='10' cols='70'>" . esc_textarea(file_get_contents($log_file_path)) . "</textarea>";

    }
}

/**
 * Campo para habilitar ou desabilitar o registro de log na página de configurações.
 */
function greenn_logging_enabled_field() {
    $logging_enabled = get_option('greenn_logging_enabled', 'no');
    echo "<input id='greenn_logging_enabled' name='greenn_logging_enabled' type='checkbox' " . checked('yes', $logging_enabled, false) . " value='yes'> ";
}

// Hooks para adicionar a página de menu e registrar as configurações no WordPress
add_action('admin_menu', 'greenn_add_admin_menu');
add_action('admin_init', 'greenn_register_settings');

/**
 * Divide um nome completo em primeiro e último nome.
 */
function split_full_name($full_name) {
    $parts = explode(' ', $full_name);
    $last_name = array_pop($parts);
    $first_name = implode(' ', $parts);
    return array($first_name, $last_name);
}
/**
 * Registra um endpoint da API REST para o webhook da Greenn.
 * Este endpoint será chamado para processar dados recebidos via POST.
 */
function greenn_webhook_endpoint() {
    register_rest_route('greenn-webhook/v1', '/process/', array(
        'methods' => 'POST',
        'callback' => 'greenn_webhook_callback',
        'permission_callback' => '__return_true', // Permite que qualquer um chame este endpoint.
    ));
}
add_action('rest_api_init', 'greenn_webhook_endpoint'); // Adiciona a ação ao inicializar a API REST.

/**
 * A função de callback que é chamada quando o endpoint do webhook é atingido.
 * Processa os dados recebidos e executa ações com base neles.
 */
function greenn_webhook_callback(WP_REST_Request $request) {
      // Log dos dados brutos recebidos
    $data_raw = $request->get_body();
    greenn_log_error("Dados brutos recebidos: " . $data_raw, true);
  
  // Obtém os dados JSON enviados para o webhook.
    $data = $request->get_json_params(); 
    if (!$data) {
        greenn_log_error('No data provided in request.');
        return new WP_REST_Response(array('message' => 'No data provided'), 400);
    }
  greenn_log_error("Dados brutos recebidos: " . $data_raw, true);


    // Verifica se todos os campos necessários estão presentes nos dados.
    $required_keys = ["seller", "client", "product", "sale"];
    foreach ($required_keys as $key) {
        if (!isset($data[$key])) {
            greenn_log_error("Missing data: $key in request.");
            return new WP_REST_Response(array('message' => "Missing data: $key"), 400);
        }
    }

    // Valida o formato dos dados recebidos.
    if (!is_array($data["client"]) || !is_array($data["product"]) || !is_array($data["sale"])) {
        greenn_log_error('Invalid data format in request.');
        return new WP_REST_Response(array('message' => 'Invalid data format'), 400);
    }

    // Valida e sanitiza o e-mail do cliente.
    $email = sanitize_email($data["client"]["email"]);
    if (!is_email($email)) {
        greenn_log_error('Invalid email address provided: ' . $email);
        return new WP_REST_Response(array('message' => 'Invalid email address'), 400);
    }

    // Sanitiza e valida o nome completo do cliente.
    $full_name = sanitize_text_field($data["client"]["name"]);
    if (empty($full_name)) {
        greenn_log_error('Full name is empty.');
        return new WP_REST_Response(array('message' => 'Full name is empty'), 400);
    }
    list($first_name, $last_name) = split_full_name($full_name); // Divide o nome completo em primeiro e último nome.
    $username = str_replace(' ', '', strtolower($full_name)); // Cria um nome de usuário a partir do nome completo, em minúsculas.

    // Verifica se o nome de usuário já existe e ajusta se necessário.
    if (username_exists($username)) {
        $suffix = 1;
        $new_username = $username . $suffix;
        while (username_exists($new_username)) {
            $suffix++;
            $new_username = $username . $suffix;
        }
        $username = $new_username;
    }

    $nickname = $full_name; // Define o apelido do usuário.
    $product_name = sanitize_text_field($data["product"]["name"]); // Sanitiza o nome do produto.
    $token = sanitize_text_field($request->get_header('authorization')); // Obtém o token de autorização do cabeçalho da requisição.

    // Processa a venda com base no status atual.
    $current_status = $data["sale"]["status"];
    if ($current_status == "refunded" || $current_status == "chargedback") {
        wc_custom_refund_order($email, $product_name); // Processa o reembolso se o status for "refunded" ou "chargedback".
    } elseif ($current_status == "paid") {
        $user = get_user_by('email', $email); // Obtém o usuário pelo e-mail.
        if (!$user) {
            // Se o usuário não existir, cria um novo.
            $password = wp_generate_password(); // Gera uma senha.
            $user_id = wp_create_user($username, $password, $email); // Cria o usuário.
            if (is_wp_error($user_id)) {
                // Se houver um erro ao criar o usuário, registra o erro e responde com falha.
                greenn_log_error("Error creating user: " . $user_id->get_error_message());
                return new WP_REST_Response(array('message' => 'Failed to create user'), 500);
            }
            // Atualiza os dados do usuário com informações fornecidas.
            wp_update_user(array('ID' => $user_id, 'first_name' => $first_name, 'last_name' => $last_name, 'nickname' => $nickname, 'display_name' => $full_name));
            send_welcome_email($email, $first_name, $password); // Envia um e-mail de boas-vindas ao novo usuário.
            $order = wc_custom_create_order_greenn(array('status' => 'completed', 'customer_id' => $user_id), $first_name, $email, $product_name); // Cria um pedido para o novo usuário.
            if (is_wp_error($order)) {
                // Se houver um erro ao criar o pedido, registra o erro e responde com falha.
                greenn_log_error("Error creating order: " . $order->get_error_message());
                return new WP_REST_Response(array('message' => 'Failed to create order'), 500);
            }
        } else {
            // Se o usuário já existir, processa o pedido para o usuário existente.
            wc_custom_process_order_for_existing_user($user->ID, $product_name);
            send_product_available_email($user->user_email, $user->first_name, $product_name); // Envia um e-mail informando que o produto está disponível.
        }
    } else {
        // Se o status da venda for desconhecido, registra o erro e responde com falha.
        greenn_log_error('Evento desconhecido: ' . $current_status);
        return new WP_REST_Response(array('message' => 'Evento desconhecido'), 400);
    }

    // Se tudo ocorrer bem, responde com sucesso.
    return new WP_REST_Response(array('success' => true, 'message' => 'Processed successfully!'), 200);
}

/**
 * Cria um pedido no WooCommerce com base nos dados fornecidos.
 * Utilizado para criar um pedido quando um novo usuário é criado após uma compra.
 *
 * @param array $order_data Dados do pedido.
 * @param string $first_name Primeiro nome do cliente.
 * @param string $email E-mail do cliente.
 * @param string $product_name Nome do produto comprado.
 * @return WC_Order|WP_Error Retorna o objeto do pedido ou um erro se algo der errado.
 */
function wc_custom_create_order_greenn($order_data, $first_name, $email, $product_name) {
    // Define o endereço de cobrança usando o nome e e-mail fornecidos.
    $address = array(
        'first_name' => $first_name,
        'email'      => $email,
    );

    // Cria um novo pedido com os dados fornecidos.
    $order = wc_create_order($order_data);
    if (is_wp_error($order)) {
        // Se houver um erro na criação do pedido, registra o erro e retorna o objeto WP_Error.
        greenn_log_error("Error creating order: " . $order->get_error_message());
        return $order;
    }

    // Tenta encontrar o produto pelo título fornecido.
    $product = get_page_by_title($product_name, OBJECT, 'product');
    if (!$product) {
        // Se o produto não for encontrado, registra o erro e retorna um novo WP_Error.
        greenn_log_error("Product not found: " . $product_name);
        return new WP_Error('product_not_found', 'Product not found');
    }

    // Adiciona o produto encontrado ao pedido, define o endereço de cobrança, calcula os totais e atualiza o status para 'completed'.
    $order->add_product(wc_get_product($product->ID), 1);
    $order->set_address($address, 'billing');
    $order->calculate_totals();
    $order->update_status("completed", '[Compra pela Greenn]');

    return $order;
}

/**
 * Processa um pedido para um usuário existente.
 * Utilizado quando um usuário existente faz uma nova compra.
 *
 * @param int $user_id ID do usuário.
 * @param string $product_name Nome do produto comprado.
 */
function wc_custom_process_order_for_existing_user($user_id, $product_name) {
    // Tenta encontrar o produto pelo título fornecido.
    $product = get_page_by_title($product_name, OBJECT, 'product');
    if (!$product) {
        // Se o produto não for encontrado, registra o erro e retorna.
        greenn_log_error("Product not found for existing user: " . $product_name);
        return;
    }

    // Cria um novo pedido.
    $order = wc_create_order();
    if (is_wp_error($order)) {
        // Se houver um erro na criação do pedido, registra o erro e retorna.
        greenn_log_error("Error creating order for existing user: " . $order->get_error_message());
        return;
    }

    // Adiciona o produto ao pedido, define o ID do cliente, calcula os totais e atualiza o status para 'completed'.
    $order->add_product(wc_get_product($product->ID), 1);
    $order->set_customer_id($user_id);
    $order->calculate_totals();
    $order->update_status('completed', 'Pedido completado automaticamente para usuário existente.', TRUE);
}

/**
 * Envia um e-mail de boas-vindas ao usuário com detalhes de login.
 *
 * @param string $email E-mail do usuário.
 * @param string $first_name Primeiro nome do usuário.
 * @param string $password Senha do usuário.
 */
function send_welcome_email($email, $first_name, $password) {
    // Define o assunto e a mensagem do e-mail.
    $subject = 'Bem-vindo ao nosso site!';
    $message = "Olá $first_name, Aqui estão seus detalhes de acesso:\nE-mail: $email\nSenha: $password\n\nAcesse agora em: https://academiadoimportador.com.br/cursos/wp-login.php e comece a aprender!";
    // Envia o e-mail.
    wp_mail($email, $subject, $message);
}

/**
 * Envia um e-mail ao usuário informando que um novo produto foi adicionado à sua conta.
 *
 * @param string $user_email E-mail do usuário.
 * @param string $user_name Nome do usuário.
 * @param string $product_name Nome do produto adicionado.
 */
function send_product_available_email($user_email, $user_name, $product_name) {
    // Define URLs úteis e o assunto e a mensagem do e-mail.
    $login_url = 'https://academiadoimportador.com.br/cursos/wp-login.php';
    $reset_password_url = 'https://academiadoimportador.com.br/cursos/wp-login.php?action=lostpassword';
    $instructions_url = 'https://academiadoimportador.com.br/login-academia-do-importador/';
    $subject = 'Seu novo curso foi adicionado à sua conta!';
    $message = "Olá $user_name,\n\n" .
               "O curso '$product_name' foi adicionado à sua conta. Você já pode acessá-lo em sua área de membros.\n\n" .
               "Acesse a plataforma: $login_url\n\n" .
               "Se você não lembra seus dados de acesso, " .
               "<a href='$reset_password_url'>clique aqui</a> para redefinir a sua senha ou veja as instruções no link a seguir: $instructions_url\n\n" .
               "Equipe";
    // Envia o e-mail com o tipo de conteúdo definido para HTML.
    wp_mail($user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
}

/**
 * Realiza o reembolso de um pedido no WooCommerce.
 * Esta função é chamada quando um evento de reembolso é recebido pelo webhook.
 *
 * @param string $email E-mail do cliente associado ao pedido.
 * @param string $product_name Nome do produto para o qual o reembolso deve ser aplicado.
 */
function wc_custom_refund_order($email, $product_name) {
    // Argumentos para buscar pedidos pelo e-mail do cliente e que estão completos ou em processamento.
    $args = array(
        'post_type'   => 'shop_order',
        'post_status' => array('wc-completed', 'wc-processing'), // Pedidos completos ou em processamento.
        'numberposts' => -1, // Buscar todos os pedidos correspondentes.
        'meta_query'  => array(
            array(
                'key'     => '_billing_email', // Chave de metadados para o e-mail de cobrança.
                'value'   => $email, // O e-mail para busca.
                'compare' => '=', // Operador de comparação.
            ),
        ),
    );

    // Busca os pedidos que correspondem aos critérios.
    $orders = get_posts($args);
    if ($orders) {
        // Itera sobre cada pedido encontrado.
        foreach ($orders as $order_post) {
            // Obtém o objeto do pedido.
            $order = wc_get_order($order_post->ID);
            if (!$order || is_wp_error($order)) {
                // Se houver um erro ao recuperar o pedido, registra o erro e continua para o próximo pedido.
                greenn_log_error("Erro ao recuperar pedido para reembolso: " . $order_post->ID);
                continue;
            }

            // Verifica se o pedido contém o produto especificado pelo nome.
            $found_product = false;
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if ($product && $product->get_name() === $product_name) {
                    $found_product = true;
                    break;
                }
            }

            if ($found_product) {
                // Se o produto for encontrado no pedido, cria um reembolso.
                $refund = wc_create_refund(array(
                    'amount'         => $order->get_total(), // O valor total do pedido é reembolsado.
                    'reason'         => 'Reembolso automático pela integração Greenn', // A razão para o reembolso.
                    'order_id'       => $order->get_id(), // O ID do pedido.
                    'line_items'     => array(), // Nenhum item específico do pedido é reembolsado.
                ));

                if (is_wp_error($refund)) {
                    // Se houver um erro ao criar o reembolso, registra o erro.
                    greenn_log_error("Error creating refund: " . $refund->get_error_message());
                } else {
                    // Atualiza o status do pedido para 'refunded' e registra a razão.
                    $order->update_status('refunded', 'Reembolso automático pela integração Greenn.');
                }
            }
        }
    } else {
        // Se nenhum pedido for encontrado, registra um erro.
        greenn_log_error("Nenhum pedido encontrado para reembolso para o e-mail: $email e produto: $product_name");
    }
} 
