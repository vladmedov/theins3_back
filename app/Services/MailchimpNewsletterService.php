<?php

namespace App\Services;

class MailchimpNewsletterService
{
    /** @return list<string> */
    public function allowedListIds(): array
    {
        $ids = [];

        foreach ($this->allLists() as $list) {
            $ids[] = $list['list_id'];
        }

        return $ids;
    }

    /** @return list<string> */
    public function allowedListIdsForLocale(string $locale): array
    {
        return array_column($this->publicListsForLocale($locale), 'list_id');
    }

    public function resolveMailchimpId(string $listId): ?string
    {
        foreach ($this->allLists() as $list) {
            if ($list['list_id'] === $listId) {
                $mailchimpId = $list['mailchimp_id'] ?? null;

                return is_string($mailchimpId) && $mailchimpId !== '' ? $mailchimpId : null;
            }
        }

        return null;
    }

    /**
     * @return list<array{list_id: string, name: string}>
     */
    public function publicListsForLocale(string $locale): array
    {
        $lists = config("services.mailchimp.lists.{$locale}", []);

        if (! is_array($lists)) {
            return [];
        }

        $public = [];

        foreach ($lists as $list) {
            if (! is_array($list)) {
                continue;
            }

            $listId = $list['list_id'] ?? null;
            $name = $list['name'] ?? null;

            if (! is_string($listId) || $listId === '' || ! is_string($name) || $name === '') {
                continue;
            }

            $public[] = [
                'list_id' => $listId,
                'name' => $name,
            ];
        }

        return $public;
    }

    /**
     * @return list<array{list_id: string, name: string, mailchimp_id: mixed}>
     */
    private function allLists(): array
    {
        $groups = config('services.mailchimp.lists', []);

        if (! is_array($groups)) {
            return [];
        }

        $all = [];

        foreach ($groups as $lists) {
            if (! is_array($lists)) {
                continue;
            }

            foreach ($lists as $list) {
                if (is_array($list) && isset($list['list_id'])) {
                    $all[] = $list;
                }
            }
        }

        return $all;
    }
}
