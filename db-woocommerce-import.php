<?php

/*
 * Plugin Name: Db WooCommerce import
 */


// PHP configuration

ini_set('memory_limit', '1024M');


// wp menu

function my_admin_menu()
{
    add_menu_page(
        __( 'Db WooCommerce import', 'my-textdomain' ),
        __( 'Db WC import', 'my-textdomain' ),
        'manage_options',
        'db-woocommerce-import',
        'db_woocommerce_import_menu',
        'dashicons-database-import',
        66
    );
}

add_action( 'admin_menu', 'my_admin_menu' );


// plugin interface

function db_woocommerce_import_menu()
{
    echo '<h1>Db WooCommerce import plugin</h1>';
    echo '<p>';
    echo '<a href="?page=db-woocommerce-import&fun=1">Import Db to WooCommerce</a><br>';
    echo '<a href="?page=db-woocommerce-import&fun=0">Clear WooCommerce</a>';
    echo '</p>';

    if (isset($_GET['fun'])) {
        echo '<h2>Result:</h2>';
        switch ($_GET['fun']) {
            case '1':
                db_woocommerce_import_groups();
                db_woocommerce_import_items();
                break;
            case '0':
                db_woocommerce_clear();
                break;
        }
    }
}


// db settings

$servername = 'localhost'; // host
$username = 'root';
$password = '';

$db = 'vhost86175s2'; // imported db
$db_wp = 'wp_test'; // wp's db

$metagroups = []; // [mg_id, mg_name, wo_id]
$groups = []; // [g_id, mg_id, g_name, wo_id]


// --- Import metagroups and groups

function db_woocommerce_import_groups()
{
    global $servername, $username, $password, $db, $metagroups, $groups;

    $conn = new mysqli($servername, $username, $password, $db);
    if ($conn->connect_error) {
        die("ERROR: Connection failed - " . $conn->connect_error);
    } else {
        echo '<p>Connection with db, Ok.</p>';
    }

    // Import metagroups
    $sql = "SELECT * FROM metagrup";
    if ($rows = $conn->query($sql)) {
        echo '<p>Create metagroups: ';
        foreach ($rows as $row) {
            $result = wp_insert_term($row["METAGROUPNAME"], 'product_cat');
            echo $row["METAGROUPNAME"] . '(' . $result['term_id'] . '), ';
            array_push($metagroups, [$row["METAGROUPNO"], $row["METAGROUPNAME"], $result['term_id']]);
        }
    } else {
        echo 'ERROR: Select metagroups from db failed.';
    }
    echo '</p>';

    // Import groups
    $groups = []; // [g_id, mg_id, g_name, wo_id]
    $sql = "SELECT * FROM artgrp";
    if ($rows = $conn->query($sql)) {
        echo '<p>Create groups: ';
        foreach ($rows as $row) {
            $key = array_search($row["METAGROUPNO"], array_column($metagroups, 0));
            $result = wp_insert_term($row["GROUPNAME"], 'product_cat', array('parent' => intval($metagroups[$key][2])));
            echo $metagroups[$key][1]. '/' . $row["GROUPNAME"] . '(' . $result['term_id'] . '), ';
            array_push($groups, [$row["GROUPNO"], $row["METAGROUPNO"], $row["GROUPNAME"], $result['term_id']]);
        }
    } else {
        echo 'ERROR: Select groups from db failed.';
    }
    echo '</p>';

    $conn->close();
}


// --- Import items

function db_woocommerce_import_items()
{
    global $servername, $username, $password, $db, $metagroups, $groups;

    $conn = new mysqli($servername, $username, $password, $db);
    if ($conn->connect_error) {
        die("ERROR: Connection failed - " . $conn->connect_error);
    } else {
        echo '<p>Connection with db, Ok.</p>';
    }

    $n = 0;
    $sql = "SELECT * FROM item";
    if ($rows = $conn->query($sql)) {
        echo '<p>Create products: ';
        foreach ($rows as $row) {
            $key = array_search($row["GROUPNO"], array_column($groups, 0));
            if ($key != false) {
                $post_id = wp_insert_post(array('post_title' => $row["ARTNAME"], 'post_type' => 'product', 'post_status' => 'publish'));
                wp_set_object_terms($post_id, $groups[$key][3], 'product_cat');
                update_post_meta( $post_id, '_price', '1.00' );
            }
            ++$n;
        }
        echo $n;
    } else {
        echo 'ERROR: Select items from db failed.';
    }
    echo '</p>';    

    $conn->close();
}


// --- Clear WooCommerce

function db_woocommerce_clear()
{
    global $servername, $username, $password, $db_wp;

    $conn = new mysqli($servername, $username, $password, $db_wp);
    if ($conn->connect_error) {
        die("ERROR: Connection failed - " . $conn->connect_error);
    } else {
        echo '<p>Connection with db, Ok.</p>';
    }

    $sql = "DELETE a,c FROM wp_terms AS a LEFT JOIN wp_term_taxonomy AS c ON a.term_id = c.term_id LEFT JOIN wp_term_relationships AS b ON b.term_taxonomy_id = c.term_taxonomy_id WHERE c.taxonomy = 'product_tag'";
    $conn->query($sql);
    $sql = "DELETE a,c FROM wp_terms AS a LEFT JOIN wp_term_taxonomy AS c ON a.term_id = c.term_id LEFT JOIN wp_term_relationships AS b ON b.term_taxonomy_id = c.term_taxonomy_id WHERE c.taxonomy = 'product_cat'";
    $conn->query($sql);

    $sql = "DELETE relations.*, taxes.*, terms.* FROM wp_term_relationships AS relations INNER JOIN wp_term_taxonomy AS taxes ON relations.term_taxonomy_id=taxes.term_taxonomy_id INNER JOIN wp_terms AS terms ON taxes.term_id=terms.term_id WHERE object_id IN (SELECT ID FROM wp_posts WHERE post_type IN ('product','product_variation'))";
    $conn->query($sql);
    $sql = "DELETE FROM wp_postmeta WHERE post_id IN (SELECT ID FROM wp_posts WHERE post_type IN ('product','product_variation'))";
    $conn->query($sql);
    $sql = "DELETE FROM wp_posts WHERE post_type IN ('product','product_variation')";
    $conn->query($sql);

    echo '<p>WooCommerce cleared.</p>';

    $conn->close();
}
