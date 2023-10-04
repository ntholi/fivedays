'use client';
import { useDisclosure } from '@mantine/hooks';
import {
  Modal,
  Button,
  Title,
  TextInput,
  Checkbox,
  Group,
  Table,
  Divider,
  NumberInput,
  ActionIcon,
} from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import { useForm } from '@mantine/form';
import { useMediaQuery } from '@mantine/hooks';
import { useState } from 'react';
import { IconPlus } from '@tabler/icons-react';

type Props = {
  courseWork: classroom_v1.Schema$CourseWork;
};

interface Element {
  title: string;
  points: number;
  description: string;
}

export default function RubricModal({ courseWork }: Props) {
  const [opened, { open, close }] = useDisclosure(false);
  const isMobile = useMediaQuery('(max-width: 768px)');
  const [elements, setElements] = useState<Element[]>([]);

  const form = useForm({
    initialValues: {
      title: '',
      points: 0,
      description: '',
    },

    validate: {
      title: (value) => value.trim().length < 1,
    },
  });

  return (
    <>
      <Modal
        opened={opened}
        title='Rubric'
        onClose={close}
        size={'70%'}
        fullScreen={isMobile}
      >
        <Divider mb='xl' />
        <Group justify='space-between'>
          <Title fw='normal' order={2}>
            {courseWork.title}
          </Title>
          <Button>Save</Button>
        </Group>
        <form
          onSubmit={form.onSubmit((values) => {
            console.log(values);
            setElements([...elements, values]);
            form.reset();
          })}
        >
          <Group mt='lg' grow>
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
          <Group justify='end'>
            <Button type='submit'>Submit</Button>
          </Group>
        </form>

        <Table mt='xl'>
          <Table.Thead>
            <Table.Tr>
              <Table.Th>Title</Table.Th>
              <Table.Th>Points</Table.Th>
              <Table.Th>Description</Table.Th>
            </Table.Tr>
          </Table.Thead>
          <Table.Tbody>
            {elements.map((element) => (
              <Table.Tr key={element.title}>
                <Table.Td>{element.title}</Table.Td>
                <Table.Td>{element.points}</Table.Td>
                <Table.Td>{element.description}</Table.Td>
              </Table.Tr>
            ))}
          </Table.Tbody>
        </Table>
      </Modal>

      <Button variant='light' onClick={open}>
        Rubric
      </Button>
    </>
  );
}
