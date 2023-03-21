<?php
/*
Plugin Name: CSV Article Exporter
Description: Exports articles to CSV format.
Version: 1.0
Author: Steve Krause
*/

// Add a new admin page for the plugin.
add_action('admin_menu', 'csv_add_admin_page');

function csv_add_admin_page() {
    add_menu_page('CSV Article Exporter', 'CSV Exporter', 'manage_options', 'csv-exporter', 'csv_admin_page');
}

function csv_admin_page() {
    // Check if the user has the required permissions.
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Handle form submission.
    if (isset($_POST['csv_export']) && check_admin_referer('csv_export_action')) {
        csv_export_articles();
    }

    // Display the admin page content.
    ?>
    <div class="wrap">
        <h1>CSV Article Exporter</h1>
        <form method="post">
            <?php wp_nonce_field('csv_export_action'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Article ID</th>
                    <td>
                        <input type="number" name="csv_article_id" min="0" value="0" />
                        <p class="description">Enter 0 to export all articles.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Export Articles', 'primary', 'csv_export'); ?>
        </form>
    </div>
    <?php
}

function csv_export_articles() {
    global $wpdb;

    // Clean any existing output buffer and start a new one.
    ob_clean();
    ob_start();

    // Get the article ID from the form and sanitize it.
    $article_id = intval($_POST['csv_article_id']);

    // Prepare the SQL query.
    $sql = "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'";

    if ($article_id > 0) {
        $sql .= $wpdb->prepare(" AND ID = %d", $article_id);
    }

    // Execute the SQL query.
    $results = $wpdb->get_results($sql, ARRAY_A);

    // Set the appropriate headers for CSV download.
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=articles.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open a file pointer connected to the output stream.
    $output = fopen('php://output', 'w');

    // Output the header row.
    fputcsv($output, array('Title', 'Content'));

    // Output the article data.
    foreach ($results as $row) {
        // Clean up the content before exporting.
        $post_content = wp_strip_all_tags($row['post_content'], true);
        $post_content = str_replace(array("\r\n", "\r", "\n"), ' ', $post_content);
        $post_content = trim(preg_replace('/\s+/', ' ', $post_content));

        fputcsv($output, array($row['post_title'], $post_content));
    }

    // Close the file pointer, terminate the script to avoid any further output, and flush the output buffer.
    fclose($output);
    ob_end_flush();
    die();
}
