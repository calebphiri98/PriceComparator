<?php
function agri_public_listing_contacts(PDO $db): array
{
    $result = [];
    $farmers = $db->query("SELECT f.id, u.fullname, u.phone FROM farmer_expected_prices f JOIN users u ON u.id = f.farmer_id WHERE f.status = 'approved'");
    foreach ($farmers->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result['farmer:' . $row['id']] = [
            'name' => (string)$row['fullname'],
            'phone' => (string)$row['phone'],
        ];
    }
    $buyers = $db->query('SELECT id, company, contact FROM outside_markets WHERE quantity > 0');
    foreach ($buyers->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result['buyer:' . $row['id']] = [
            'name' => (string)$row['company'],
            'phone' => (string)$row['contact'],
        ];
    }
    return $result;
}
