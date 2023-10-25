'use client';
import { addCriteria } from './actions';
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
    await addCriteria(courseId, courseWorkId, formToJSON(formData));
    ref.current?.reset();
  };

  return (
    <form action={handleSubmit} ref={ref}>
      <Stack>
        <TextInput name='title' label='Title' required />
        <NumberInput name='points' label='Points' required />
        <TextInput name='description' label='Description' />
        <SubmitButton>Add</SubmitButton>
      </Stack>
    </form>
  );
}
