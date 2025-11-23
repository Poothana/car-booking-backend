<?php

namespace App\Repositories;

use App\Models\Car;
use Illuminate\Http\UploadedFile;

interface CarRepositoryInterface
{
    /**
     * Create a new car with additional details.
     *
     * @param array $data
     * @param UploadedFile|null $image
     * @return Car
     */
    public function create(array $data, ?UploadedFile $image = null): Car;

    /**
     * Update an existing car with additional details.
     *
     * @param int $id
     * @param array $data
     * @param UploadedFile|null $image
     * @param string|null $imageName
     * @return Car
     */
    public function update(int $id, array $data, ?UploadedFile $image = null, ?string $imageName = null): Car;
}

