<?php
/*
Plugin Name: CSV Article Exporter
Description: Exports articles to CSV format.
Version: 1.4
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

    // Declare and initialize $article_not_found
    $article_not_found = false;

    // Handle form submission and update $article_not_found.
    if (isset($_POST['csv_export']) && check_admin_referer('csv_export_action')) {
        $article_not_found = csv_export_articles();
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
            <?php if ($article_not_found) : ?>
                <div style="color: red; margin-bottom: 10px;"><?php echo esc_html__('Error: The specified article ID was not found.'); ?></div>
            <?php endif; ?>
            <?php submit_button('Export Articles', 'primary', 'csv_export'); ?>
        </form>
    </div>
    <?php
}

function csv_export_articles() {
    global $wpdb;

    // Get the article ID from the form and sanitize it.
    $article_id = absint($_POST['csv_article_id']);

    // Prepare the SQL query.
    $sql = "SELECT {$wpdb->posts}.ID, post_title, post_name as slug, post_content, {$wpdb->users}.display_name as author, {$wpdb->posts}.post_date as publish_date FROM {$wpdb->posts} LEFT JOIN {$wpdb->users} ON {$wpdb->posts}.post_author = {$wpdb->users}.ID WHERE post_type = 'post' AND post_status = 'publish'";

    if ($article_id > 0) {
        $sql .= $wpdb->prepare(" AND {$wpdb->posts}.ID = %d", $article_id);
    }

    // Execute the SQL query.
    $results = $wpdb->get_results($sql, ARRAY_A);

    $article_not_found = false;

    if ($article_id > 0 && empty($results)) {
        $article_not_found = true;
    } else {
        // Start the CSV export process when the article is found.
        if (!$article_not_found) {
            // Clean any existing output buffer and start a new one.
            ob_clean();
            ob_start();
		
		// Set the appropriate headers for CSV download.
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=articles.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open a file pointer connected to the output stream.
        $output = fopen('php://output', 'w');

    // Output the header row.
    fputcsv($output, array('Title', 'Author', 'Publish Date', 'Slug URL', 'Content'));

    // Output the article data.
    foreach ($results as $row) {
        // Clean up the content before exporting.
        $post_content = wp_strip_all_tags($row['post_content'], true);
        $post_content = str_replace(array("\r\n", "\r", "\n"), ' ', $post_content);
        $post_content = trim(preg_replace('/\s+/', ' ', $post_content));

        // Generate the full slug URL.
        $slug_url = get_permalink($row['ID']);

        fputcsv($output, array($row['post_title'], $row['author'], $row['publish_date'], $slug_url, $post_content));
    }

        // Close the file pointer, terminate the script to avoid any further output, and flush the output buffer.
        fclose($output);
        ob_end_flush();
        die();
    	}
	}
    // Return the $article_not_found value
    return $article_not_found;
}