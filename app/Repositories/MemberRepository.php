<?php

declare(strict_types=1);

namespace PawBerry\Repositories;

use PawBerry\Core\Database;
use PDO;

final class MemberRepository
{
    /** @return list<array<string, mixed>> */
    public function allActive(): array
    {
        $stmt = Database::connection()->query(
            'SELECT * FROM members
             WHERE archived_at IS NULL
             ORDER BY name ASC, id ASC'
        );
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function allArchived(): array
    {
        $stmt = Database::connection()->query(
            'SELECT * FROM members
             WHERE archived_at IS NOT NULL
             ORDER BY archived_at DESC, name ASC'
        );
        return $stmt->fetchAll();
    }

    public function countArchived(): int
    {
        $stmt = Database::connection()->query(
            'SELECT COUNT(*) FROM members WHERE archived_at IS NOT NULL'
        );
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM members WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array{
     *   name: string,
     *   species: string,
     *   breed: ?string,
     *   sex: ?string,
     *   birth_date: ?string,
     *   microchip_number: ?string,
     *   description: ?string,
     *   notes: ?string,
     *   photo_original?: ?string,
     *   photo_cropped?: ?string
     * } $data
     */
    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO members (
                name, species, breed, sex, birth_date, microchip_number,
                description, notes, photo_original, photo_cropped,
                created_at, updated_at
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
             )'
        );
        $stmt->execute([
            $data['name'],
            $data['species'],
            $data['breed'],
            $data['sex'],
            $data['birth_date'],
            $data['microchip_number'],
            $data['description'],
            $data['notes'],
            $data['photo_original'] ?? null,
            $data['photo_cropped'] ?? null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @param array{
     *   name: string,
     *   species: string,
     *   breed: ?string,
     *   sex: ?string,
     *   birth_date: ?string,
     *   microchip_number: ?string,
     *   description: ?string,
     *   notes: ?string,
     *   photo_original?: ?string,
     *   photo_cropped?: ?string
     * } $data
     */
    public function update(int $id, array $data): void
    {
        $fields = [
            'name' => $data['name'],
            'species' => $data['species'],
            'breed' => $data['breed'],
            'sex' => $data['sex'],
            'birth_date' => $data['birth_date'],
            'microchip_number' => $data['microchip_number'],
            'description' => $data['description'],
            'notes' => $data['notes'],
        ];

        $sql = 'UPDATE members SET
                    name = ?, species = ?, breed = ?, sex = ?, birth_date = ?,
                    microchip_number = ?, description = ?, notes = ?,
                    updated_at = NOW()';
        $params = array_values($fields);

        if (array_key_exists('photo_original', $data)) {
            $sql .= ', photo_original = ?';
            $params[] = $data['photo_original'];
        }
        if (array_key_exists('photo_cropped', $data)) {
            $sql .= ', photo_cropped = ?';
            $params[] = $data['photo_cropped'];
        }

        $sql .= ' WHERE id = ?';
        $params[] = $id;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public function archive(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE members SET archived_at = NOW(), updated_at = NOW() WHERE id = ? AND archived_at IS NULL'
        );
        $stmt->execute([$id]);
    }

    public function restore(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE members SET archived_at = NULL, updated_at = NOW() WHERE id = ? AND archived_at IS NOT NULL'
        );
        $stmt->execute([$id]);
    }
}
