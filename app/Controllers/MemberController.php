<?php

declare(strict_types=1);

namespace PawBerry\Controllers;

use PawBerry\Core\Request;
use PawBerry\Core\Response;
use PawBerry\Core\Session;
use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Repositories\EventRepository;
use PawBerry\Repositories\MemberRepository;
use PawBerry\Services\MemberPhotoService;
use RuntimeException;
use Throwable;

final class MemberController
{
    private MemberRepository $members;
    private MemberPhotoService $photos;
    private EventRepository $events;

    public function __construct()
    {
        $this->members = new MemberRepository();
        $this->photos = new MemberPhotoService();
        $this->events = new EventRepository();
    }

    public function index(Request $request): Response
    {
        $showArchived = (string) $request->query('archived', '') === '1';
        $active = $this->members->allActive();
        $archived = $showArchived ? $this->members->allArchived() : [];
        $archivedCount = $this->members->countArchived();

        return View::render('members/index', [
            'title' => 'The Gang',
            'nav' => 'gang',
            'members' => $active,
            'archivedMembers' => $archived,
            'showArchived' => $showArchived,
            'archivedCount' => $archivedCount,
        ]);
    }

    public function show(Request $request): Response
    {
        $member = $this->requireMember($request);
        if ($member instanceof Response) {
            return $member;
        }

        $memberId = (int) $member['id'];

        return View::render('members/show', [
            'title' => (string) $member['name'],
            'nav' => 'gang',
            'member' => $member,
            'recentEvents' => $this->events->recentForMember($memberId, 5),
            'eventCount' => $this->events->countForMember($memberId),
        ]);
    }

    public function createForm(Request $request): Response
    {
        return View::render('members/form', [
            'title' => 'Add Member',
            'nav' => 'gang',
            'mode' => 'create',
            'member' => $this->emptyMember(),
            'errors' => [],
            'old' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $parsed = $this->parseInput($request);
        $errors = $this->validate($parsed);

        $hasPhoto = isset($_FILES['photo']) && (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $crop = $this->parseCropData((string) $request->input('crop_data', ''));
        $upload = null;

        if ($hasPhoto && $crop === null) {
            $errors['photo'] = 'Please crop the photograph before saving.';
        }

        if ($hasPhoto && $crop !== null && $errors === []) {
            try {
                $upload = $this->photos->validateUpload($_FILES['photo']);
            } catch (RuntimeException $e) {
                $errors['photo'] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            return View::render('members/form', [
                'title' => 'Add Member',
                'nav' => 'gang',
                'mode' => 'create',
                'member' => $this->emptyMember(),
                'errors' => $errors,
                'old' => $parsed,
            ]);
        }

        $id = $this->members->create($parsed);

        try {
            if ($upload !== null && $crop !== null) {
                $photoPaths = $this->photos->storeNewPhoto($id, $upload, $crop);
                $this->members->update($id, array_merge($parsed, [
                    'photo_original' => $photoPaths['original'],
                    'photo_cropped' => $photoPaths['cropped'],
                ]));
            }
        } catch (Throwable $e) {
            // Member exists without a photo; keep the row and let the keeper retry on edit.
            Session::flash('error', 'The member was saved, but the photograph could not be processed. You can add it from Edit.');
            return Response::redirect(Url::to('/gang/' . $id . '/edit'));
        }

        Session::flash('success', $parsed['name'] . ' joined The Gang.');
        Session::flash('sound', 'member');
        return Response::redirect(Url::to('/gang/' . $id));
    }

    public function editForm(Request $request): Response
    {
        $member = $this->requireMember($request);
        if ($member instanceof Response) {
            return $member;
        }

        return View::render('members/form', [
            'title' => 'Edit Member',
            'nav' => 'gang',
            'mode' => 'edit',
            'member' => $member,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function update(Request $request): Response
    {
        $member = $this->requireMember($request);
        if ($member instanceof Response) {
            return $member;
        }

        $id = (int) $member['id'];
        $parsed = $this->parseInput($request);
        $errors = $this->validate($parsed);

        $hasPhoto = isset($_FILES['photo']) && (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $recropOnly = (string) $request->input('recrop_only', '') === '1';
        $crop = $this->parseCropData((string) $request->input('crop_data', ''));

        if (($hasPhoto || $recropOnly) && $crop === null) {
            $errors['photo'] = 'Please confirm the photograph crop before saving.';
        }
        if ($recropOnly && empty($member['photo_original'])) {
            $errors['photo'] = 'There is no original photograph to recrop.';
        }

        if ($errors !== []) {
            return View::render('members/form', [
                'title' => 'Edit Member',
                'nav' => 'gang',
                'mode' => 'edit',
                'member' => $member,
                'errors' => $errors,
                'old' => $parsed,
            ]);
        }

        $oldOriginal = $member['photo_original'] ?? null;
        $oldCropped = $member['photo_cropped'] ?? null;
        $newOriginal = null;
        $newCropped = null;

        try {
            if ($hasPhoto && $crop !== null) {
                $upload = $this->photos->validateUpload($_FILES['photo']);
                $paths = $this->photos->storeNewPhoto($id, $upload, $crop);
                $newOriginal = $paths['original'];
                $newCropped = $paths['cropped'];
            } elseif ($recropOnly && $crop !== null && is_string($oldOriginal) && $oldOriginal !== '') {
                $paths = $this->photos->recropExisting($id, $oldOriginal, $crop);
                $newOriginal = $paths['original'];
                $newCropped = $paths['cropped'];
            }

            $update = $parsed;
            if ($newCropped !== null) {
                $update['photo_original'] = $newOriginal;
                $update['photo_cropped'] = $newCropped;
            }

            $this->members->update($id, $update);

            // Delete old files only after a successful DB update + new files exist.
            if ($newCropped !== null) {
                if (is_string($oldCropped) && $oldCropped !== '' && $oldCropped !== $newCropped) {
                    $this->photos->deleteRelative($oldCropped);
                }
                if (
                    $hasPhoto
                    && is_string($oldOriginal)
                    && $oldOriginal !== ''
                    && $oldOriginal !== $newOriginal
                ) {
                    $this->photos->deleteRelative($oldOriginal);
                }
            }
        } catch (RuntimeException $e) {
            // Clean up any newly written files if DB update did not commit paths.
            if ($newCropped !== null && $newCropped !== $oldCropped) {
                $this->photos->deleteRelative($newCropped);
            }
            if ($hasPhoto && $newOriginal !== null && $newOriginal !== $oldOriginal) {
                $this->photos->deleteRelative($newOriginal);
            }

            return View::render('members/form', [
                'title' => 'Edit Member',
                'nav' => 'gang',
                'mode' => 'edit',
                'member' => array_merge($member, $parsed),
                'errors' => ['photo' => $e->getMessage()],
                'old' => $parsed,
            ]);
        }

        Session::flash('success', $parsed['name'] . ' was updated.');
        return Response::redirect(Url::to('/gang/' . $id));
    }

    public function archive(Request $request): Response
    {
        $member = $this->requireMember($request);
        if ($member instanceof Response) {
            return $member;
        }

        $this->members->archive((int) $member['id']);
        Session::flash('success', $member['name'] . ' was archived.');
        return Response::redirect(Url::to('/gang'));
    }

    public function restore(Request $request): Response
    {
        $member = $this->requireMember($request);
        if ($member instanceof Response) {
            return $member;
        }

        $this->members->restore((int) $member['id']);
        Session::flash('success', $member['name'] . ' is back in The Gang.');
        return Response::redirect(Url::to('/gang/' . $member['id']));
    }

    public function photo(Request $request): Response
    {
        $member = $this->requireMember($request);
        if ($member instanceof Response) {
            return $member;
        }

        $variant = (string) $request->param('variant', 'cropped');
        if (!in_array($variant, ['cropped', 'original'], true)) {
            return View::error(404, 'Photo not found', 'That photograph could not be found.');
        }

        $relative = $variant === 'original'
            ? ($member['photo_original'] ?? null)
            : ($member['photo_cropped'] ?? null);

        if (!is_string($relative) || $relative === '') {
            return View::error(404, 'Photo not found', 'That photograph could not be found.');
        }

        try {
            $absolute = $this->photos->absolutePath($relative);
        } catch (RuntimeException) {
            return View::error(404, 'Photo not found', 'That photograph could not be found.');
        }

        if (!is_file($absolute)) {
            return View::error(404, 'Photo not found', 'That photograph could not be found.');
        }

        $mime = $this->photos->mimeForPath($absolute);
        return Response::file($absolute, $mime);
    }

    /** @return array<string, mixed>|Response */
    private function requireMember(Request $request): array|Response
    {
        $id = (int) $request->param('id', 0);
        if ($id < 1) {
            return View::error(404, 'Member not found', 'That member could not be found.');
        }

        $member = $this->members->find($id);
        if ($member === null) {
            return View::error(404, 'Member not found', 'That member could not be found.');
        }

        return $member;
    }

    /** @return array<string, mixed> */
    private function emptyMember(): array
    {
        return [
            'id' => null,
            'name' => '',
            'species' => '',
            'breed' => '',
            'sex' => '',
            'birth_date' => '',
            'microchip_number' => '',
            'description' => '',
            'notes' => '',
            'photo_original' => null,
            'photo_cropped' => null,
            'archived_at' => null,
        ];
    }

    /**
     * @return array{
     *   name: string,
     *   species: string,
     *   breed: ?string,
     *   sex: ?string,
     *   birth_date: ?string,
     *   microchip_number: ?string,
     *   description: ?string,
     *   notes: ?string
     * }
     */
    private function parseInput(Request $request): array
    {
        $nullIfEmpty = static function (string $value): ?string {
            $value = trim($value);
            return $value === '' ? null : $value;
        };

        return [
            'name' => trim((string) $request->input('name', '')),
            'species' => trim((string) $request->input('species', '')),
            'breed' => $nullIfEmpty((string) $request->input('breed', '')),
            'sex' => $nullIfEmpty((string) $request->input('sex', '')),
            'birth_date' => $nullIfEmpty((string) $request->input('birth_date', '')),
            'microchip_number' => $nullIfEmpty((string) $request->input('microchip_number', '')),
            'description' => $nullIfEmpty((string) $request->input('description', '')),
            'notes' => $nullIfEmpty((string) $request->input('notes', '')),
        ];
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
     *   notes: ?string
     * } $data
     * @return array<string, string>
     */
    private function validate(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = 'Please enter a name.';
        } elseif (mb_strlen($data['name']) > 120) {
            $errors['name'] = 'Name should be 120 characters or fewer.';
        }

        if ($data['species'] === '') {
            $errors['species'] = 'Please enter a species.';
        } elseif (mb_strlen($data['species']) > 120) {
            $errors['species'] = 'Species should be 120 characters or fewer.';
        }

        foreach (['breed' => 120, 'sex' => 40, 'microchip_number' => 64] as $field => $max) {
            if ($data[$field] !== null && mb_strlen($data[$field]) > $max) {
                $errors[$field] = 'That value is too long.';
            }
        }

        if ($data['birth_date'] !== null) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $data['birth_date']);
            $valid = $dt && $dt->format('Y-m-d') === $data['birth_date'];
            if (!$valid) {
                $errors['birth_date'] = 'Please enter a valid birth date.';
            } elseif ($dt > new \DateTimeImmutable('today')) {
                $errors['birth_date'] = 'Birth date cannot be in the future.';
            }
        }

        if ($data['description'] !== null && mb_strlen($data['description']) > 5000) {
            $errors['description'] = 'Description is a bit too long.';
        }
        if ($data['notes'] !== null && mb_strlen($data['notes']) > 5000) {
            $errors['notes'] = 'Notes are a bit too long.';
        }

        return $errors;
    }

    /** @return array{x: float, y: float, width: float, height: float, rotate: float}|null */
    private function parseCropData(string $json): ?array
    {
        if (trim($json) === '') {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        $x = (float) ($data['x'] ?? NAN);
        $y = (float) ($data['y'] ?? NAN);
        $width = (float) ($data['width'] ?? NAN);
        $height = (float) ($data['height'] ?? NAN);
        $rotate = (float) ($data['rotate'] ?? 0);

        if (
            !is_finite($x)
            || !is_finite($y)
            || !is_finite($width)
            || !is_finite($height)
            || !is_finite($rotate)
            || $width < 1
            || $height < 1
        ) {
            return null;
        }

        return [
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'rotate' => $rotate,
        ];
    }
}

