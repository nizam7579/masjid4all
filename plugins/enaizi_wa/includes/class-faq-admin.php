<?php

class Niz_WA_FAQ_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
    }

    public function add_menu() {
        add_menu_page(
            'FAQ Manager',
            'FAQ Manager',
            'manage_options',
            'enaizi-wa-faq',
            [$this, 'render_page'],
            'dashicons-editor-help'
        );
    }

    public function render_page() {
        global $wpdb;

        $table = $wpdb->prefix . 'niz_wa_faq';

        if (!empty($_GET['delete'])) {
            $wpdb->delete($table, [
                'id' => intval($_GET['delete'])
            ]);
        }

        if (!empty($_POST['save_faq'])) {
            $data = [
                'category' => sanitize_text_field($_POST['category']),
                'question' => sanitize_text_field($_POST['question']),
                'answer' => sanitize_textarea_field($_POST['answer']),
                'keywords' => sanitize_text_field($_POST['keywords']),
                'status' => intval($_POST['status']),
                'updated_at' => current_time('mysql')
            ];

            if (!empty($_POST['faq_id'])) {
                $wpdb->update(
                    $table,
                    $data,
                    ['id' => intval($_POST['faq_id'])]
                );
            } else {
                $data['created_at'] = current_time('mysql');
                $wpdb->insert($table, $data);
            }
        }

        $edit = null;

        if (!empty($_GET['edit'])) {
            $edit = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table WHERE id = %d",
                    intval($_GET['edit'])
                ),
                ARRAY_A
            );
        }

        $faqs = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY id DESC",
            ARRAY_A
        );
        ?>

        <div class="wrap">
            <h1>FAQ Manager</h1>

            <form method="post">
                <input type="hidden" name="faq_id" value="<?php echo esc_attr($edit['id'] ?? ''); ?>">

                <table class="form-table">
                    <tr>
                        <th>Category</th>
                        <td><input type="text" name="category" class="regular-text" value="<?php echo esc_attr($edit['category'] ?? ''); ?>"></td>
                    </tr>

                    <tr>
                        <th>Question</th>
                        <td><input type="text" name="question" class="regular-text" value="<?php echo esc_attr($edit['question'] ?? ''); ?>"></td>
                    </tr>

                    <tr>
                        <th>Answer</th>
                        <td><textarea name="answer" rows="5" class="large-text"><?php echo esc_textarea($edit['answer'] ?? ''); ?></textarea></td>
                    </tr>

                    <tr>
                        <th>Keywords</th>
                        <td><input type="text" name="keywords" class="regular-text" value="<?php echo esc_attr($edit['keywords'] ?? ''); ?>"></td>
                    </tr>

                    <tr>
                        <th>Status</th>
                        <td>
                            <select name="status">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p>
                    <button class="button button-primary" name="save_faq">
                        Save FAQ
                    </button>
                </p>
            </form>

            <hr>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category</th>
                        <th>Question</th>
                        <th>Keywords</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($faqs as $faq): ?>
                    <tr>
                        <td><?php echo $faq['id']; ?></td>
                        <td><?php echo esc_html($faq['category']); ?></td>
                        <td><?php echo esc_html($faq['question']); ?></td>
                        <td><?php echo esc_html($faq['keywords']); ?></td>
                        <td><?php echo $faq['status'] ? 'Active' : 'Inactive'; ?></td>
                        <td>
                            <a href="?page=enaizi-wa-faq&edit=<?php echo $faq['id']; ?>">Edit</a> |
                            <a href="?page=enaizi-wa-faq&delete=<?php echo $faq['id']; ?>">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        </div>

        <?php
    }
}