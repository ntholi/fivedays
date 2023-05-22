import { axiosInstance } from '@/lib/config/axios';
import { Button, Group, TextInput, Textarea } from '@mantine/core';
import { useForm } from '@mantine/form';
import { useSession } from 'next-auth/react';
import { useRouter } from 'next/router';
import React from 'react';

export default function AssessmentForm() {
  const router = useRouter();
  const { data: session } = useSession();
  const [loading, setLoading] = React.useState(false);

  const form = useForm({
    initialValues: {
      title: '',
      description: '',
      workType: 'ASSIGNMENT',
      state: 'PUBLISHED',
    },
  });

  async function submit(value: any) {
    const { id } = router.query;
    setLoading(true);
    try {
      console.log(id, value);
      const response = await axiosInstance(session)?.post(
        `/courses/${id}/courseWork`,
        value
      );
      console.log(response);
    } catch (error) {
      console.log(error);
    }
    setLoading(false);
  }

  return (
    <>
      <form onSubmit={form.onSubmit(submit)}>
        <TextInput
          withAsterisk
          label='Title'
          {...form.getInputProps('title')}
        />

        <Textarea
          label='Description'
          minRows={8}
          {...form.getInputProps('description')}
        />

        <Group position='right' mt='md'>
          <Button type='submit' loading={loading}>
            Assign
          </Button>
        </Group>
      </form>
    </>
  );
}
