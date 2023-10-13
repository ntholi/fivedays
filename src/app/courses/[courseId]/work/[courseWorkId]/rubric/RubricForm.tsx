'use client';
import { addRubricItem } from './actions';
import { NumberInput, Stack, TextInput } from '@mantine/core';
import { useRef } from 'react';
import SubmitButton from '@/app/core/SubmitButton';

type Props = {
  courseId: string;
  courseWorkId: string;
};
export default function RubricForm({ courseId, courseWorkId }: Props) {
  const addRubricWithId = addRubricItem.bind(null, courseId, courseWorkId);
  const ref = useRef<HTMLFormElement>(null);

  const handleSubmit = async (formData: FormData) => {
    await addRubricWithId(formData);
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
