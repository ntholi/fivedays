import {
  ActionIcon,
  Button,
  Container,
  Divider,
  Flex,
  Group,
  NumberInput,
  Stack,
  TextInput,
  Title,
  Table,
  Text,
} from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconTrash } from '@tabler/icons-react';
import React from 'react';
import axios from 'axios';

type Props = {
  questionId?: string | null;
};

export default function RubricDisplay({ questionId }: Props) {
  const [data, setData] = React.useState<Rubric[]>([]);

  // TODO: handle null or undefined questionId

  const submitRubric = async () => {
    const response = await axios.post('/api/rubric/create', {
      questionId: questionId,
      data: data,
    });
    console.log(response);
  };

  const form = useForm<Rubric>({
    initialValues: {
      questionId: questionId,
      title: '',
      description: '',
      points: 0,
    },
  });

  function deleteItem(rubric: Rubric) {
    setData(data.filter((it) => it.title !== rubric.title));
  }

  function addItem(rubric: Rubric) {
    if (rubric.points > 0) {
      setData([rubric, ...data]);
      form.reset();
    }
  }

  return (
    <>
      <Title order={3} fw='normal'>
        <Flex justify='space-between'>
          <Text>Rubric</Text>
          <Button onClick={submitRubric}>Save</Button>
        </Flex>
      </Title>
      <Divider my='sm' />
      <Container size='md'>
        <form onSubmit={form.onSubmit(addItem)}>
          <Stack spacing='sm'>
            <Group grow>
              <TextInput
                required
                label='Title'
                {...form.getInputProps('title')}
              />
              <NumberInput
                required
                label='Points'
                {...form.getInputProps('points')}
              />
            </Group>
            <TextInput
              label='Description'
              {...form.getInputProps('description')}
            />
            <Flex justify='flex-end'>
              <Button type='submit' variant='outline'>
                Add
              </Button>
            </Flex>
          </Stack>
        </form>
        <Table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Points</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            {data.map((it) => (
              <tr key={it.title}>
                <td>{it.title}</td>
                <td>{it.points}</td>
                <td>{it.description}</td>
                <td>
                  <ActionIcon color='red' onClick={() => deleteItem(it)}>
                    <IconTrash size='1.125rem' />
                  </ActionIcon>
                </td>
              </tr>
            ))}
          </tbody>
        </Table>
      </Container>
    </>
  );
}
