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
import RubricForm from './RubricForm';
import { classroom_v1 } from 'googleapis';

type Props = {
  assessment: classroom_v1.Schema$CourseWork;
  close: () => void;
};

export default function RubricDisplay({ assessment, close }: Props) {
  const [data, setData] = React.useState<Rubric[]>([]);

  // TODO: handle null or undefined questionId

  const submitRubric = async () => {
    const response = await axios.post('/api/rubric/create', {
      questionId: assessment.id,
      data: data,
    });
    close();
  };

  function deleteItem(rubric: Rubric) {
    setData(data.filter((it) => it.title !== rubric.title));
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
        <RubricForm assessment={assessment} setData={setData} data={data} />
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
