'use client';
import { addRubricItem } from './actions';
import { Divider, NumberInput, Stack, TextInput, Title } from '@mantine/core';
import { useRef } from 'react';
import SubmitButton from '@/app/core/SubmitButton';
import { formToJSON } from 'axios';

type Props = {
  courseId: string;
  courseWorkId: string;
};
export default function RubricForm({ courseId, courseWorkId }: Props) {
  const ref = useRef<HTMLFormElement>(null);

  const handleSubmit = async (formData: FormData) => {
    await addRubricItem(courseId, courseWorkId, formToJSON(formData));
    ref.current?.reset();
  };

  return (
    <form action={handleSubmit} ref={ref}>
      <Title order={2}>Rubric</Title>
      <Divider mt='xs' mb='md' />
      <Stack>
        <TextInput name='title' label='Title' required />
        <NumberInput name='points' label='Points' required />
        <TextInput name='description' label='Description' />
        <SubmitButton>Add</SubmitButton>
      </Stack>
    </form>
  );
}
