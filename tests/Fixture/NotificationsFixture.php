<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * NotificationsFixture
 */
class NotificationsFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 'f25b8b8a-ec05-49b1-8ff8-fb6b2c9bf528',
                'user_id' => '9b1afe3c-700b-45e7-ba38-8a691b1b3dfe',
                'channel' => 'Lorem ipsum do',
                'type' => 'Lorem ipsum dolor sit amet',
                'severity' => 'Lorem ipsum do',
                'title' => 'Lorem ipsum dolor sit amet',
                'message' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'action_url' => 'Lorem ipsum dolor sit amet',
                'action_label' => 'Lorem ipsum dolor sit amet',
                'is_read' => 1,
                'read_at' => '2025-10-22 12:46:26',
                'created' => '2025-10-22 12:46:26',
                'modified' => '2025-10-22 12:46:26',
            ],
        ];
        parent::init();
    }
}
