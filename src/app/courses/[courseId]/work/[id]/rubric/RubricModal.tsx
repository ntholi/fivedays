'use client';
import { useDisclosure } from '@mantine/hooks';
import {
  Modal,
  Button,
  Title,
  TextInput,
  Group,
  Divider,
  NumberInput,
} from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import { useForm } from '@mantine/form';
import { useMediaQuery } from '@mantine/hooks';
import axios from 'axios';

type Props = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function RubricModal({ courseWork }: Props) {
  const [opened, { open, close }] = useDisclosure(false);
  const isMobile = useMediaQuery('(max-width: 768px)');

  const form = useForm({
    initialValues: {
      title: '',
      points: 0,
      description: '',
    },

    validate: {
      title: (value) => {
        return value.trim().length > 0 ? null : 'Title is required';
      },
    },
  });

  const handleSubmit = async (values: typeof form.values) => {
    try {
      const { data } = await axios.post('/api/rubric', {
        courseId: courseWork.courseId,
        courseWorkId: courseWork.id,
        title: values.title,
        points: values.points,
        description: values.description,
      });
    } finally {
      form.reset();
    }
  };

  return (
    <>
      <Modal
        opened={opened}
        title='Rubric'
        onClose={close}
        size={'xl'}
        fullScreen={isMobile}
      >
        <Divider mb='xl' />
        <Title fw='normal' order={2}>
          {courseWork.title}
        </Title>
        <form onSubmit={form.onSubmit(handleSubmit)}>
          <Group
            mt='lg'
            grow
            styles={{
              root: {
                alignItems: 'start',
              },
            }}
          >
            <TextInput
              label='Title'
              {...form.getInputProps('title')}
              required
            />
            <NumberInput
              label='Points'
              {...form.getInputProps('points')}
              required
            />
          </Group>
          <TextInput
            label='Description'
            {...form.getInputProps('description')}
          />
          <Group mt={'lg'} justify='end'>
            <Button type='submit'>Add</Button>
          </Group>
        </form>

        <Divider my='xl' />
      </Modal>

      <Button variant='light' onClick={open}>
        Rubric
      </Button>
    </>
  );
}
