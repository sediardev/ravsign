<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profile" />

    <h1 class="sr-only">Profile</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile"
            description="Update your name and email address"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-[18px]"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-1.5">
                <Label for="name">Full name</Label>
                <Input
                    id="name"
                    class="block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-1.5">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    class="block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="you@company.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
