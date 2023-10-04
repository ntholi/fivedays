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
  Stack,
  LoadingOverlay,
} from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import { useForm } from '@mantine/form';
import { useMediaQuery } from '@mantine/hooks';
import { useEffect, useState } from 'react';
import axios from 'axios';
import { Rubric, RubricCriterion } from '@prisma/client';

type Props = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function RubricModal({ courseWork }: Props) {
  const [opened, { open, close }] = useDisclosure(false);
  const isMobile = useMediaQuery('(max-width: 768px)');
  const [elements, setElements] = useState<RubricCriterion[]>([]);
  const [adding, setAdding] = useState(false);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    setLoading(true);
    axios
      .get(`/api/rubric?courseWorkId=${courseWork.id}`)
      .then(({ data }) => {
        const rubric = data.rubric as Rubric & {
          rubricCriteria: RubricCriterion[];
        };
        if (rubric) {
          setElements(rubric.rubricCriteria);
        }
      })
      .finally(() => setLoading(false));
  }, [courseWork.id]);

  const form = useForm({
    initialValues: {
      title: '',
      points: 0,
      description: '',
    },

    validate: {
      title: (value) => {
        if (elements.find((it) => it.title === value)) {
          return 'Title already exists';
        }
        return value.trim().length > 0 ? null : 'Title is required';
      },
    },
  });

  const handleSubmit = async (values: typeof form.values) => {
    setAdding(true);
    try {
      const { data } = await axios.post('/api/rubric', {
        courseId: courseWork.courseId,
        courseWorkId: courseWork.id,
        title: values.title,
        points: values.points,
        description: values.description,
      });
      if (data.rubricCriteria) {
        setElements((current) => [...current, data.rubricCriteria]);
      }
    } finally {
      setAdding(false);
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
        <LoadingOverlay
          visible={loading}
          zIndex={1000}
          overlayProps={{ radius: 'sm', blur: 2 }}
        />

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
            <Button type='submit' loading={adding}>
              Add
            </Button>
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
